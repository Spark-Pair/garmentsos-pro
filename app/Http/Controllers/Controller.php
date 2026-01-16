<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\BankAccount;
use App\Models\CR;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\Order;
use App\Models\PaymentProgram;
use App\Models\PhysicalQuantity;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function home() {
        $today = Carbon::today();
        $fiveDaysLater = Carbon::today()->addDays(5);

        // Get the count of unpaid bills that are due or due within 5 days
        $count = UtilityBill::where('is_paid', false)
            ->where(function ($query) use ($today, $fiveDaysLater) {
                $query->whereBetween('due_date', [$today, $fiveDaysLater])
                    ->orWhereDate('due_date', '<', $today);
            })
            ->count();

        $notification = [];

        if ($count > 0) {
            $notification = [
                'title' => 'Utility Bill Reminder',
                'message' => "{$count} Utility Bill" . ($count === 1 ? '' : 's') . " Unpaid or Due Soon",
            ];
        }

        return view('home', compact('notification'));
    }

    public function getCategoryData(Request $request)
    {
        switch ($request->category) {
            case 'supplier':
                $suppliers = Supplier::whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->select('id', 'supplier_name')->get()->makeHidden('creator', 'categories');

                foreach ($suppliers as $supplier) {
                    $supplier['balance'] = 0;
                    $supplier['balance'] = number_format($supplier['balance'], 1, '.', ',');
                }

                return $suppliers;
                break;

            case 'customer':
                $customers = Customer::with('city:id,title')->whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->select('id', 'customer_name', 'city_id')->get()->makeHidden('creator');

                return $customers;
                break;

            case 'self_account':
                $selfAccount = BankAccount::with('subCategory', 'bank')->where('category', 'self')->get();
                return $selfAccount;
                break;

            default:
                return "Not Found";
                break;
        }
    }

    public function changeDataLayout(Request $request)
    {
        $previousRoute = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();

        $authUser = Auth::user();

        $layout = [];

        if (!empty($authUser->layout)) {
            // Parse the existing layout from JSON
            $layout = json_decode($authUser->layout, true);
        }

        $newLayout = $request->layout == 'grid' ? 'table' : 'grid';

        // Update the layout for the specified page
        $layout[$previousRoute] = $newLayout;

        // Save the updated layout back to the user
        $authUser->layout = json_encode($layout);

        $authUser->save();

        return response()->json([
            "status" => "updated",
            "updatedLayout" => $newLayout
        ]);
    }

    protected function getAuthLayout($routeName, $default = 'grid')
    {
        $layout = Auth::user()->layout ?? '';

        if (!empty($layout)) {
            $layout = json_decode($layout, true);
            return $layout[$routeName] ?? $default;
        }

        return $default;
    }

    protected function checkRole($roles)
    {
        if (!in_array(Auth::user()->role, $roles)) {
            return false;
        }

        return true;
    }

    public function getOrderDetails(Order $order, Request $request)
    {
        $validator = Validator::make($request->all(), [
            "order_no" => "required|exists:orders,order_no",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $order = Order::with('customer.city', 'articles.article')->where("order_no", $request->order_no)->first();

        if (!$order) {
            return response()->json(["error" => "Order not found."]);
        }

        if ($order->status == 'invoiced') {
            return response()->json(["error" => "This order has already been invoiced."]);
        }


        if (!$request->boolean('only_order')) {
            $orderedArticles = $order->articles;

            // $articleIds = array_map(fn($oa) => $oa->id, $orderedArticles);
            // $articles = Article::whereIn('id', $articleIds)->get()->keyBy('id');

            $stockErrors = [];

            foreach ($orderedArticles as $orderedArticle) {

                $article = $orderedArticle->article;

                if (!$article) {
                    $stockErrors[] = "Article with ID {$orderedArticle->article_id} not found.";
                    continue;
                }

                // $orderedArticle->article = $article;
                $orderedArticle->total_quantity_in_packets = 0;

                $totalPhysicalStockPackets = PhysicalQuantity::where("article_id", $article->id)->sum('packets');

                if ($totalPhysicalStockPackets > 0 && $article->pcs_per_packet > 0) {
                    $availablePhysicalQuantity = $article->sold_quantity > 0
                        ? $totalPhysicalStockPackets - ($article->sold_quantity / $article->pcs_per_packet)
                        : $totalPhysicalStockPackets;

                    $orderedPackets = $orderedArticle->ordered_pcs / $article->pcs_per_packet;
                    $invoiceQty = $orderedArticle->dispatched_pcs ?? 0;
                    $pendingPackets = $orderedPackets - ($invoiceQty / $article->pcs_per_packet);

                    $orderedArticle->total_quantity_in_packets = floor(min($pendingPackets, $availablePhysicalQuantity));
                }

                $actualQuantity = $orderedArticle->total_quantity_in_packets * $article->pcs_per_packet;

                if ($actualQuantity == 0) {
                    $stockErrors[] = 'Stock is less than order quantity for article: ' . $article->article_no;
                }
            }

            // if (!empty($stockErrors)) {
            //     return response()->json(['error' => implode("; ", $stockErrors)]);
            // }

            // $order->articles = array_values($orderedArticles);
        }

        if (empty($order->articles)) {
            return response()->json(['error' => 'No articles found for this order.']);
        }

        return response()->json($order);
    }


    public function getProgramDetails(Request $request) {
        $validator = Validator::make($request->all(), [
            "program_no" => "required|exists:payment_programs,program_no",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $paymentProgram = PaymentProgram::with('customer', 'subCategory', 'order')->where("program_no", $request->program_no)->where('customer_id', $request->customer_id)->first();

        if ($paymentProgram->sub_category_type == "App\Models\BankAccount") {
            $paymentProgram->load('subCategory.bank');
        }

        $bankAccount = BankAccount::with('bank', 'subCategory')->where('sub_category_type', $paymentProgram->sub_category_type)->where('sub_category_id', $paymentProgram->sub_category_id)->get();

        if (count($bankAccount) > 0) {
            $paymentProgram->bank_accounts = $bankAccount;
        }

        return response()->json([
            'status' => 'success',
            'data' => $paymentProgram,
        ]);
    }

    public function setInvoiceType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "invoice_type" => "required|in:order,shipment",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->invoice_type = $request->invoice_type;
        $user->save();

        session()->flash('success', 'Invoice type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice type set as default.',
        ]);
    }

    public function setVoucherType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "voucher_type" => "required|in:supplier,self_account",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->voucher_type = $request->voucher_type;
        $user->save();

        session()->flash('success', 'Voucher type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher type set as default.',
        ]);
    }

    public function setProductionType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "production_type" => "required|in:issue,receive",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->production_type = $request->production_type;
        $user->save();

        session()->flash('success', 'Production type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Production type set as default.',
        ]);
    }

    public function getShipmentDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "shipment_no" => "required|exists:shipments,shipment_no",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // Get shipment by number
        $shipment = Shipment::where('shipment_no', $request->shipment_no)->with('articles.article')->first();

        if (!$shipment) {
            return response()->json(['error' => 'Shipment not found']);
        }

        // Only continue if not filtering by only_order
        $validArticles = [];

        foreach ($shipment->Articles as $articleData) {
            $article = $articleData['article'];

            if (!$article) continue;

            // Total stock from PhysicalQuantity
            $totalPackets = PhysicalQuantity::where("article_id", $article['id'])->sum("packets");

            // Available quantity calculation
            $availablePackets = $article['sold_quantity'] > 0
                ? $totalPackets - ($article['sold_quantity'] / $article['pcs_per_packet'])
                : $totalPackets;

            $availableStock = max(0, floor($availablePackets * $article['pcs_per_packet'])); // convert packets to pcs
            $articleData['article'] = $article;
            $articleData['available_stock'] = $availableStock;

            // Required shipment quantity (in pcs)
            $requiredShipmentQty = $articleData['shipment_pcs'];

            // Check if available stock is enough
            if ($availableStock < $requiredShipmentQty) {
                return response()->json(['error' => 'Stock is less than shipment quantity for article: ' . $article['article_no']]);
            }

            $validArticles[] = $articleData;
        }

        // Replace articles with valid filtered ones
        $shipment->Articles = $validArticles;

        if (count($shipment->Articles) === 0) {
            return response()->json(['error' => 'No articles found for this shipment']);
        }

        $Allcustomers = Customer::with(['invoices.shipment', 'user', 'city'])
            ->whereIn('category', ['regular', 'site'])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->when(strtolower(string: $shipment->city) === 'karachi', function ($query) {
                $query->whereHas('city', function ($q) {
                    $q->where('title', 'Karachi');
                });
            })
            ->when(strtolower($shipment->city) === 'lahore', function ($query) {
                $query->whereHas('city', function ($q) {
                    $q->where('title', '!=', 'Karachi');
                });
            })
            // For 'all', no city filter
            ->get();

        $Customers = $Allcustomers->filter(function ($customer) use ($shipment) {
            // Check if any of the customer's invoices match the shipment number
            return !$customer->invoices->contains(function ($invoice) use ($shipment) {
                return
                $invoice->shipment_no == $shipment->shipment_no ||
                ($invoice->shipment && $invoice->shipment->date == $shipment->date);
            });
        })->values()->toArray();

        return response()->json([
            'status' => 'success',
            'shipment' => $shipment,
            'customers' => $Customers,
        ]);
    }

    public function getVoucherDetails(Request $request)
    {
        $voucher = Voucher::where('voucher_no', $request->voucher_no)
            ->with([
                'supplier:id,supplier_name',
                'payments.cheque.customer.city',
                'payments.slip.customer.city',
                'payments.cheque.paymentClearRecord',
                'payments.slip.paymentClearRecord',
            ])
            ->first();

        // Case 1: Voucher not found
        if (!$voucher) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid voucher number.'
            ]);
        }

        // Case 2: No payments at all
        if ($voucher->payments->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No payments found for this voucher.'
            ]);
        }

        $payments = [];
        $hasChequeOrSlip = false;

        foreach ($voucher->payments as $payment) {
            // --- Cheque ---
            $chequeNotCleared = false;
            if ($payment->cheque) {
                if (!$payment->cheque->is_return) {
                    $hasChequeOrSlip = true;

                    $clearAmount  = $payment->cheque->paymentClearRecord->sum('amount');
                    $hasClearDate = !is_null($payment->cheque->clear_date);

                    // agar amount = 0 aur clear_date null hai tabhi "not cleared"
                    $chequeNotCleared = ($clearAmount == 0 && !$hasClearDate);
                }
            }

            // --- Slip ---
            $slipNotCleared = false;
            if ($payment->slip) {
                if (!$payment->slip->is_return) {
                    $hasChequeOrSlip = true;

                    $clearAmount  = $payment->slip->paymentClearRecord->sum('amount');
                    $hasClearDate = !is_null($payment->slip->clear_date);

                    $slipNotCleared = ($clearAmount == 0 && !$hasClearDate);
                }
            }

            if ($chequeNotCleared || $slipNotCleared) {
                $payments[] = [
                    'id' => $payment->id,
                    'payment_id' => $payment->cheque_id ?? $payment->slip_id,
                    'date' => $payment->slip->slip_date ?? $payment->cheque->cheque_date ?? $payment->date,
                    'method' => $payment->cheque ? 'cheque' : ($payment->slip ? 'slip' : ''),
                    'reff_no' => $payment->cheque->cheque_no ?? $payment->slip->slip_no,
                    'amount' => $payment->cheque->amount ?? $payment->slip->amount,
                    'customer_name' => $payment->cheque ? ($payment->cheque->customer?->customer_name . ' | ' . $payment->cheque->customer?->city?->short_title) : ($payment->slip ? ($payment->slip->customer?->customer_name . ' | ' . $payment->slip->customer?->city?->short_title) : null),
                ];
            }
        }

        // Case 3: No cheque or slip inside payments
        if (!$hasChequeOrSlip) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No cheque or slip found for this voucher.'
            ]);
        }

        // Case 4: All cheques/slips cleared
        if (empty($payments)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'All cheques and slips for this voucher are cleared.'
            ]);
        }

        // Success response
        $mappedVoucher = [
            'id'            => $voucher->id,
            'voucher_no'    => $voucher->voucher_no,
            'date'          => $voucher->date,
            'amount'        => $voucher->amount,
            'supplier_name' => $voucher->supplier?->supplier_name ?? app('client_company')->name,
            'supplier_id'   => $voucher->supplier_id,
            'payments'      => $payments,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $mappedVoucher
        ]);
    }

    public function getEmployeesByCategory(Request $request)
    {

        $employees = Employee::where('category', $request->category)->where('status', 'active')->with('type')
            ->whereHas('type', function ($query) {
                $query->where('title', 'not like', '% | E%');
            })
            ->get();
        return response()->json([
            'status' => 'success',
            'data' => $employees
        ]);
    }

    public function setDailyLedgerType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "daily_ledger_type" => "required|in:deposit,use",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->daily_ledger_type = $request->daily_ledger_type;
        $user->save();

        session()->flash('success', 'Daily ledger type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Daily ledger type set as default.',
        ]);
    }

    public function setStatementType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "statement_type" => "required|in:summarized,detailed",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->statement_type = $request->statement_type;
        $user->save();

        session()->flash('success', 'Statement type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Statement type set as default.',
        ]);
    }

    public function getUtilityAccounts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bill_type_id' => 'required|integer|exists:setups,id',
            'location_id' => 'required|integer|exists:setups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $utilityAccounts = UtilityAccount::where('bill_type_id', $request->bill_type_id)->where('location_id', $request->location_id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $utilityAccounts,
        ]);
    }
}
