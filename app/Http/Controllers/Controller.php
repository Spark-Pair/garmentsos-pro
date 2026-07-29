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
use App\Models\Shipment;
use App\Services\ArticleStockService;
use App\Services\Branches\BranchModuleRegistryService;
use App\Services\Branches\ModuleBranchService;
use App\Models\Supplier;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function isCustomerRole(): bool
    {
        return Auth::user()?->role === 'customer';
    }

    protected function isSupplierRole(): bool
    {
        return Auth::user()?->role === 'supplier';
    }

    protected function currentCustomer(): ?Customer
    {
        $userId = Auth::id();
        if (!$userId) {
            return null;
        }

        return Customer::where('user_id', $userId)->first();
    }

    protected function currentSupplier(): ?Supplier
    {
        $userId = Auth::id();
        if (!$userId) {
            return null;
        }

        return Supplier::where('user_id', $userId)->first();
    }

    protected function articleStockMap($articleIds, ?int $excludeOrderId = null, ?int $branchId = null)
    {
        return app(ArticleStockService::class)->summaries($articleIds, $excludeOrderId, $branchId);
    }

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
                })->select('id', 'supplier_name', 'date')->get()->makeHidden('creator', 'categories');

                return collect($this->supplierOptionPayloads($suppliers))->values();
                break;

            case 'customer':
                $customers = Customer::with('city:id,title')->whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->select('id', 'customer_name', 'date', 'city_id')->get()->makeHidden('creator');

                return collect($this->customerOptionPayloads($customers))->values();
                break;

            case 'self_account':
                $accounts = BankAccount::with('subCategory', 'bank')
                    ->where('category', 'self')
                    ->get();

                return collect($this->bankAccountOptionPayloads($accounts))->values();
                break;

            default:
                return "Not Found";
                break;
        }
    }

    public function changeDataLayout(Request $request)
    {
        $previousRoute = $request->route_name;
        if (empty($previousRoute)) {
            $previousRoute = app('router')
                ->getRoutes()
                ->match(app('request')->create(url()->previous()))
                ->getName();
        }

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
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'developer') {
            return true;
        }

        return in_array($user->role, $roles, true);
    }

    protected function dependencyCounts(array $dependencies): array
    {
        $counts = [];

        foreach ($dependencies as $label => $dependency) {
            if (is_callable($dependency)) {
                $count = (int) $dependency();
            } else {
                [$table, $column, $value] = $dependency;
                $count = Schema::hasTable($table) && Schema::hasColumn($table, $column)
                    ? (int) DB::table($table)->where($column, $value)->count()
                    : 0;
            }

            if ($count > 0) {
                $counts[$label] = $count;
            }
        }

        return $counts;
    }

    protected function dependencyBlockMessage(string $recordLabel, array $counts): string
    {
        $summary = collect($counts)
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->implode(', ');

        return "{$recordLabel} has connected records and was not deleted. Review these first: {$summary}.";
    }

    protected function denyIfNoRole(array $roles, string $message = 'You do not have permission to access this page.', string $redirectRoute = 'home')
    {
        if ($this->checkRole($roles)) {
            return null;
        }

        return redirect(route($redirectRoute))->with('error', $message);
    }

    protected function applyDocumentNumberLookup(Builder $query, string $column, ?string $value): Builder
    {
        $candidates = $this->documentNumberCandidates($value);

        if (empty($candidates)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($lookup) use ($column, $candidates) {
            foreach ($candidates as $candidate) {
                $lookup->orWhere($column, $candidate)
                    ->orWhere($column, 'like', '%-' . $candidate);
            }
        });
    }

    protected function documentNumberCandidates(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $candidates = [$value];

        if (preg_match('/^[A-Z0-9]+-[A-Z0-9]+-(.+)$/i', $value, $matches)) {
            $candidates[] = $matches[1];
        }

        if (preg_match('/^[A-Z]+-(.+)$/i', $value, $matches)) {
            $candidates[] = $matches[1];
        }

        foreach ($candidates as $candidate) {
            if (preg_match('/^\d+$/', $candidate)) {
                $candidates[] = str_pad($candidate, 4, '0', STR_PAD_LEFT);
                $candidates[] = str_pad($candidate, 6, '0', STR_PAD_LEFT);
            }

            if (preg_match('/^(\d{2})-(\d+)$/', $candidate, $matches)) {
                $candidates[] = $matches[1] . '-' . str_pad($matches[2], 4, '0', STR_PAD_LEFT);
            }
        }

        return array_values(array_unique(array_filter($candidates, fn ($candidate) => trim((string) $candidate) !== '')));
    }

    public function getOrderDetails(Order $order, Request $request)
    {
        $validator = Validator::make($request->all(), [
            "order_no" => "required|string",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // Load order with customer, city, and articles
        $branchService = app(ModuleBranchService::class);
        $orderQuery = $branchService->applyRelatedScope(Order::with([
            'customer.city',
            'articles.article' => fn ($q) => $q->withSum('invoiceArticles as sold_pcs', 'invoice_pcs'),
        ]), 'orders', 'invoices');
        $order = $this->applyDocumentNumberLookup($orderQuery, 'order_no', $request->order_no)->first();

        if (!$order) {
            return response()->json(["error" => "Order not found."]);
        }

        $allowInvoiced = $request->boolean('allow_invoiced') && Auth::user()?->role === 'developer';
        if ($order->status === 'invoiced' && !$allowInvoiced) {
            return response()->json(["error" => "This order has already been invoiced."]);
        }

        if (!$request->boolean('only_order')) {
            $stockErrors = [];
            $stockMap = $this->articleStockMap(
                $order->articles->pluck('article_id'),
                $order->id,
                $branchService->shouldFilterRecords('physical_quantities') ? $branchService->selectedBranchIdForModule('invoices') : null
            );

            // Filter out articles with 0 stock or missing
            $filteredArticles = $order->articles->filter(function ($orderedArticle) use (&$stockErrors, $stockMap) {

                $article = $orderedArticle->article;

                if (!$article) {
                    $stockErrors[] = "Article with ID {$orderedArticle->article_id} not found.";
                    return false; // remove missing articles
                }

                $orderedArticle->total_quantity_in_packets = 0;

                if (($article->pcs_per_packet ?? 0) > 0) {
                    $availablePcs = (float) ($stockMap->get($article->id)['current_stock_pcs'] ?? 0);

                    $orderedPackets = ($orderedArticle->ordered_pcs ?? 0) / $article->pcs_per_packet;
                    $invoiceQty = max(0, (int) ($orderedArticle->dispatched_pcs ?? 0));
                    $pendingPackets = max(0, $orderedPackets - ($invoiceQty / $article->pcs_per_packet));

                    $orderedArticle->total_quantity_in_packets = floor(min($pendingPackets, $availablePcs / $article->pcs_per_packet));
                }

                $actualQuantity = (int) ($orderedArticle->total_quantity_in_packets ?? 0)
                                * (int) ($article->pcs_per_packet ?? 0);

                if ($actualQuantity <= 0) {
                    $stockErrors[] = "Stock is less than order quantity for article: {$article->article_no}";
                    return false; // remove articles with 0 stock
                }

                return true; // keep valid articles
            });

            $order->setRelation('articles', $filteredArticles->values());

            // Optional: return stock errors if needed
            // if (!empty($stockErrors)) {
            //     return response()->json(['error' => implode("; ", $stockErrors)]);
            // }
        }

        if ($order->articles->isEmpty()) {
            return response()->json(['error' => 'No articles found for this order.']);
        }

        return response()->json($this->formatOrderDetailsPayload($order));
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
            "shipment_no" => "required|string",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // Get shipment by number
        $branchService = app(ModuleBranchService::class);
        $shipmentQuery = $branchService->applyRelatedScope(Shipment::query(), 'shipments', 'invoices');
        $shipment = $this->applyDocumentNumberLookup($shipmentQuery, 'shipment_no', $request->shipment_no)
            ->with([
                'articles.article' => fn ($q) => $q->withSum('invoiceArticles as sold_pcs', 'invoice_pcs'),
            ])
            ->first();

        if (!$shipment) {
            return response()->json(['error' => 'Shipment not found']);
        }

        // Only continue if not filtering by only_order
        $validArticles = [];
        $stockMap = $this->articleStockMap($shipment->articles->pluck('article_id'));

        foreach ($shipment->Articles as $articleData) {
            $article = $articleData['article'];

            if (!$article) continue;

            if ((float) ($article['pcs_per_packet'] ?? 0) <= 0) {
                return response()->json(['error' => 'Master unit is missing for article: ' . $article['article_no']]);
            }

            $availableStock = (int) ($stockMap->get((int) $article['id'])['current_stock_pcs'] ?? 0);
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

        $Allcustomers = Customer::with(['invoices.shipment', 'user:id,status', 'city'])
            ->withSum('invoices as total_invoice_amount', 'netAmount')
            ->withSum(['payments as total_paid_amount' => fn($q) => $q->where('type', '!=', 'DR')], 'amount')
            ->withSum(['statementAdjustments as adjustment_plus_amount' => fn($q) => $q->where('direction', 'plus')], 'amount')
            ->withSum(['statementAdjustments as adjustment_minus_amount' => fn($q) => $q->where('direction', 'minus')], 'amount')
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
            ->select('id', 'user_id', 'customer_name', 'urdu_title', 'category', 'phone_number', 'city_id')
            ->get();

        $Customers = $Allcustomers->filter(function ($customer) use ($shipment) {
            // Check if any of the customer's invoices match the shipment number
            return !$customer->invoices->contains(function ($invoice) use ($shipment) {
                return
                $invoice->shipment_no == $shipment->shipment_no ||
                ($invoice->shipment && $invoice->shipment->date == $shipment->date);
            });
        })->values()->map(fn ($customer) => $this->formatInvoiceCustomerPayload($customer))->toArray();

        return response()->json([
            'status' => 'success',
            'shipment' => $this->formatShipmentDetailsPayload($shipment),
            'customers' => $Customers,
        ]);
    }

    private function formatOrderDetailsPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'date' => $order->date?->format('Y-m-d'),
            'discount' => $order->discount,
            'netAmount' => $order->netAmount,
            'deliver_to' => $order->deliver_to,
            'customer' => $this->formatInvoiceCustomerPayload($order->customer),
            'articles' => $order->articles->map(fn ($item) => [
                'id' => $item->id,
                'article_id' => $item->article_id,
                'description' => $item->description,
                'ordered_pcs' => $item->ordered_pcs,
                'dispatched_pcs' => $item->dispatched_pcs,
                'total_quantity_in_packets' => $item->total_quantity_in_packets,
                'article' => $this->formatInvoiceArticlePayload($item->article),
            ])->values()->all(),
        ];
    }

    private function formatShipmentDetailsPayload(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'shipment_no' => $shipment->shipment_no,
            'date' => $shipment->date?->format('Y-m-d'),
            'discount' => $shipment->discount,
            'netAmount' => $shipment->netAmount,
            'city' => $shipment->city,
            'articles' => collect($shipment->Articles)->map(fn ($item) => [
                'id' => $item->id,
                'shipment_id' => $item->shipment_id,
                'article_id' => $item->article_id,
                'description' => $item->description,
                'shipment_pcs' => $item->shipment_pcs,
                'available_stock' => $item->available_stock,
                'article' => $this->formatInvoiceArticlePayload($item->article),
            ])->values()->all(),
        ];
    }

    private function formatInvoiceArticlePayload(?Article $article): ?array
    {
        if (!$article) {
            return null;
        }

        return [
            'id' => $article->id,
            'article_no' => $article->article_no,
            'pcs_per_packet' => $article->pcs_per_packet,
            'sales_rate' => $article->sales_rate,
            'category' => $article->category,
            'fabric_type' => $article->fabric_type,
            'season' => $article->season,
            'size' => $article->size,
            'image' => $article->image,
        ];
    }

    private function formatInvoiceCustomerPayload(?Customer $customer): ?array
    {
        if (!$customer) {
            return null;
        }

        $balance = $this->customerBalance($customer);

        return [
            'id' => $customer->id,
            'customer_name' => $customer->customer_name,
            'urdu_title' => $customer->urdu_title,
            'category' => $customer->category,
            'phone_number' => $customer->phone_number,
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'user' => $customer->user ? [
                'id' => $customer->user->id,
                'status' => $customer->user->status,
            ] : null,
            'city' => $customer->city ? [
                'id' => $customer->city->id,
                'title' => $customer->city->title,
                'short_title' => $customer->city->short_title,
            ] : null,
        ];
    }

    protected function customerBalance(Customer $customer): float
    {
        $scope = $this->balanceBranchScope();

        return (float) $customer->calculateBalance(
            branchIds: $scope['branch_ids'],
            includeNullBranchRecords: $scope['include_null_branch_records'],
        );
    }

    protected function customerOptionPayload(Customer $customer): array
    {
        $balance = $this->customerBalance($customer);

        return [
            'id' => $customer->id,
            'customer_name' => $customer->customer_name,
            'date' => $customer->date?->format('Y-m-d'),
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'city' => $customer->city ? [
                'id' => $customer->city->id,
                'title' => $customer->city->title,
                'short_title' => $customer->city->short_title,
            ] : null,
        ];
    }

    protected function customerOptionPayloads(iterable $customers, ?string $moduleKey = null): array
    {
        $customers = collect($customers);
        $balances = $this->customerBalances($customers->pluck('id')->all(), $moduleKey);

        return $customers
            ->mapWithKeys(fn (Customer $customer) => [
                (int) $customer->id => $this->customerOptionPayloadFromBalance(
                    $customer,
                    (float) ($balances[(int) $customer->id] ?? 0)
                ),
            ])
            ->all();
    }

    protected function customerOptionPayloadFromBalance(Customer $customer, float $balance): array
    {
        return [
            'id' => $customer->id,
            'customer_name' => $customer->customer_name,
            'date' => $customer->date?->format('Y-m-d'),
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'city' => $customer->city ? [
                'id' => $customer->city->id,
                'title' => $customer->city->title,
                'short_title' => $customer->city->short_title,
            ] : null,
        ];
    }

    protected function supplierBalance(Supplier $supplier, mixed $toDate = null): float
    {
        $scope = $this->balanceBranchScope();

        return (float) ($toDate
            ? $supplier->calculateBalance(null, $toDate, false, true, $scope['branch_ids'], $scope['include_null_branch_records'])
            : $supplier->calculateBalance(branchIds: $scope['branch_ids'], includeNullBranchRecords: $scope['include_null_branch_records']));
    }

    protected function supplierOptionPayload(Supplier $supplier, mixed $toDate = null): array
    {
        $balance = $this->supplierBalance($supplier);
        $balanceAtDate = $toDate ? $this->supplierBalance($supplier, $toDate) : $balance;

        return [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'date' => optional($supplier->date)->toJSON(),
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'balance_at_date' => $balanceAtDate,
            'balance_at_date_formatted' => \App\Support\Money::format($balanceAtDate),
        ];
    }

    protected function supplierOptionPayloads(iterable $suppliers, ?string $moduleKey = null): array
    {
        $suppliers = collect($suppliers);
        $balances = $this->supplierBalances($suppliers->pluck('id')->all(), $moduleKey);

        return $suppliers
            ->mapWithKeys(fn (Supplier $supplier) => [
                (int) $supplier->id => $this->supplierOptionPayloadFromBalance(
                    $supplier,
                    (float) ($balances[(int) $supplier->id] ?? 0)
                ),
            ])
            ->all();
    }

    protected function supplierOptionPayloadFromBalance(Supplier $supplier, float $balance): array
    {
        return [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'date' => optional($supplier->date)->toJSON(),
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'balance_at_date' => $balance,
            'balance_at_date_formatted' => \App\Support\Money::format($balance),
        ];
    }

    protected function employeeOptionPayload(Employee $employee): array
    {
        $scope = $this->balanceBranchScope();
        $balance = (float) $employee->calculateBalance(
            branchIds: $scope['branch_ids'],
            includeNullBranchRecords: $scope['include_null_branch_records'],
        );

        return [
            'id' => $employee->id,
            'employee_name' => $employee->employee_name,
            'category' => $employee->category,
            'joining_date' => $employee->joining_date?->format('Y-m-d'),
            'type' => $employee->type,
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
        ];
    }

    protected function employeeOptionPayloads(iterable $employees, ?string $moduleKey = null): array
    {
        $employees = collect($employees);
        $balances = $this->employeeBalances($employees->pluck('id')->all(), $moduleKey);

        return $employees
            ->mapWithKeys(fn (Employee $employee) => [
                (int) $employee->id => $this->employeeOptionPayloadFromBalance(
                    $employee,
                    (float) ($balances[(int) $employee->id] ?? 0)
                ),
            ])
            ->all();
    }

    protected function employeeOptionPayloadFromBalance(Employee $employee, float $balance): array
    {
        return [
            'id' => $employee->id,
            'employee_name' => $employee->employee_name,
            'category' => $employee->category,
            'joining_date' => $employee->joining_date?->format('Y-m-d'),
            'type' => $employee->type,
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
        ];
    }

    protected function bankAccountBalance(BankAccount $account): float
    {
        $scope = $this->balanceBranchScope();

        return (float) $account->calculateBalance(
            branchIds: $scope['branch_ids'],
            includeNullBranchRecords: $scope['include_null_branch_records'],
        );
    }

    protected function bankAccountOptionPayload(BankAccount $account): array
    {
        $balance = $this->bankAccountBalance($account);

        return [
            'id' => $account->id,
            'account_title' => $account->account_title,
            'account_no' => $account->account_no,
            'category' => $account->category,
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'bank' => $account->bank ? [
                'id' => $account->bank->id,
                'title' => $account->bank->title,
                'short_title' => $account->bank->short_title,
            ] : null,
            'sub_category' => $account->subCategory ? [
                'id' => $account->subCategory->id,
                'supplier_name' => $account->subCategory->supplier_name ?? null,
                'customer_name' => $account->subCategory->customer_name ?? null,
            ] : null,
        ];
    }

    protected function bankAccountOptionPayloads(iterable $accounts, ?string $moduleKey = null): array
    {
        $accounts = collect($accounts);
        $balances = $this->bankAccountBalances($accounts->pluck('id')->all(), $moduleKey);

        return $accounts
            ->mapWithKeys(fn (BankAccount $account) => [
                (int) $account->id => $this->bankAccountOptionPayloadFromBalance(
                    $account,
                    (float) ($balances[(int) $account->id] ?? 0)
                ),
            ])
            ->all();
    }

    protected function bankAccountOptionPayloadFromBalance(BankAccount $account, float $balance): array
    {
        return [
            'id' => $account->id,
            'account_title' => $account->account_title,
            'account_no' => $account->account_no,
            'category' => $account->category,
            'balance' => $balance,
            'balance_formatted' => \App\Support\Money::format($balance),
            'bank' => $account->bank ? [
                'id' => $account->bank->id,
                'title' => $account->bank->title,
                'short_title' => $account->bank->short_title,
            ] : null,
            'sub_category' => $account->subCategory ? [
                'id' => $account->subCategory->id,
                'supplier_name' => $account->subCategory->supplier_name ?? null,
                'customer_name' => $account->subCategory->customer_name ?? null,
            ] : null,
        ];
    }

    protected function balanceBranchScope(?string $moduleKey = null): array
    {
        try {
            $branches = app(ModuleBranchService::class);
            $moduleKey ??= $this->balanceModuleKey($branches);

            if (!$moduleKey || !$branches->shouldFilterRecords($moduleKey)) {
                return [
                    'branch_ids' => [],
                    'include_null_branch_records' => false,
                ];
            }

            $branchIds = collect($branches->selectedBranchIdsForModule($moduleKey) ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($branchIds === []) {
                $selectedBranchId = $branches->selectedBranchIdForModule($moduleKey);
                if ($selectedBranchId) {
                    $branchIds = [(int) $selectedBranchId];
                }
            }

            $mainBranchId = $branches->mainBranch()?->id;

            return [
                'branch_ids' => $branchIds,
                'include_null_branch_records' => (bool) (
                    $mainBranchId && in_array((int) $mainBranchId, $branchIds, true)
                ),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'branch_ids' => [],
                'include_null_branch_records' => false,
            ];
        }
    }

    protected function applyBulkBalanceBranchScope(mixed $query, string $table, array $scope): void
    {
        $branchIds = $scope['branch_ids'] ?? [];

        if ($branchIds === [] || !Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        $query->where(function ($nested) use ($branchIds, $scope, $table) {
            $nested->whereIn($table . '.branch_id', $branchIds);

            if ($scope['include_null_branch_records'] ?? false) {
                $nested->orWhereNull($table . '.branch_id');
            }
        });
    }

    protected function normalizeBalanceIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function customerBalances(array $customerIds, ?string $moduleKey = null): array
    {
        $customerIds = $this->normalizeBalanceIds($customerIds);

        if ($customerIds === []) {
            return [];
        }

        $scope = $this->balanceBranchScope($moduleKey);

        $invoiceTotals = DB::table('invoices')
            ->select('customer_id', DB::raw('COALESCE(SUM(netAmount), 0) as total'))
            ->whereIn('customer_id', $customerIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'invoices', $scope))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $paymentTotals = DB::table('customer_payments')
            ->select('customer_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('customer_id', $customerIds)
            ->where('type', '!=', 'DR')
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'customer_payments', $scope))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $adjustmentTotals = DB::table('statement_adjustments')
            ->select('adjustable_id', DB::raw("COALESCE(SUM(CASE WHEN direction = 'minus' THEN -amount ELSE amount END), 0) as total"))
            ->where('adjustable_type', Customer::class)
            ->whereIn('adjustable_id', $customerIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'statement_adjustments', $scope))
            ->groupBy('adjustable_id')
            ->pluck('total', 'adjustable_id');

        return collect($customerIds)
            ->mapWithKeys(fn ($customerId) => [
                $customerId => (float) ($invoiceTotals[$customerId] ?? 0)
                    - (float) ($paymentTotals[$customerId] ?? 0)
                    + (float) ($adjustmentTotals[$customerId] ?? 0),
            ])
            ->all();
    }

    protected function supplierBalances(array $supplierIds, ?string $moduleKey = null): array
    {
        $supplierIds = $this->normalizeBalanceIds($supplierIds);

        if ($supplierIds === []) {
            return [];
        }

        $scope = $this->balanceBranchScope($moduleKey);

        $expenseTotals = DB::table('expenses')
            ->select('supplier_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('supplier_id', $supplierIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'expenses', $scope))
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        $paymentTotals = DB::table('supplier_payments')
            ->select('supplier_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('supplier_id', $supplierIds)
            ->whereRaw('LOWER(method) IN (?, ?, ?, ?, ?, ?, ?, ?)', [
                'cheque',
                'cash',
                'slip',
                'atm',
                'self cheque',
                'program',
                'p. return',
                'adjustment',
            ])
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'supplier_payments', $scope))
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        $productionTotals = DB::table('productions')
            ->join('suppliers', 'suppliers.worker_id', '=', 'productions.worker_id')
            ->select('suppliers.id as supplier_id', DB::raw('COALESCE(SUM(productions.amount), 0) as total'))
            ->whereIn('suppliers.id', $supplierIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'productions', $scope))
            ->groupBy('suppliers.id')
            ->pluck('total', 'supplier_id');

        $adjustmentTotals = DB::table('statement_adjustments')
            ->select('adjustable_id', DB::raw("COALESCE(SUM(CASE WHEN direction = 'minus' THEN -amount ELSE amount END), 0) as total"))
            ->where('adjustable_type', Supplier::class)
            ->whereIn('adjustable_id', $supplierIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'statement_adjustments', $scope))
            ->groupBy('adjustable_id')
            ->pluck('total', 'adjustable_id');

        return collect($supplierIds)
            ->mapWithKeys(fn ($supplierId) => [
                $supplierId => (float) ($expenseTotals[$supplierId] ?? 0)
                    + (float) ($productionTotals[$supplierId] ?? 0)
                    - (float) ($paymentTotals[$supplierId] ?? 0)
                    + (float) ($adjustmentTotals[$supplierId] ?? 0),
            ])
            ->all();
    }

    protected function employeeBalances(array $employeeIds, ?string $moduleKey = null): array
    {
        $employeeIds = $this->normalizeBalanceIds($employeeIds);

        if ($employeeIds === []) {
            return [];
        }

        $scope = $this->balanceBranchScope($moduleKey);

        $productionTotals = DB::table('productions')
            ->select('worker_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('worker_id', $employeeIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'productions', $scope))
            ->groupBy('worker_id')
            ->pluck('total', 'worker_id');

        $paymentTotals = DB::table('employee_payments')
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'employee_payments', $scope))
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $salaryTotals = DB::table('salaries')
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'salaries', $scope))
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        return collect($employeeIds)
            ->mapWithKeys(fn ($employeeId) => [
                $employeeId => (float) ($productionTotals[$employeeId] ?? 0)
                    + (float) ($salaryTotals[$employeeId] ?? 0)
                    - (float) ($paymentTotals[$employeeId] ?? 0),
            ])
            ->all();
    }

    protected function bankAccountBalances(array $accountIds, ?string $moduleKey = null): array
    {
        $accountIds = $this->normalizeBalanceIds($accountIds);

        if ($accountIds === []) {
            return [];
        }

        $scope = $this->balanceBranchScope($moduleKey);

        $accounts = DB::table('bank_accounts')
            ->whereIn('id', $accountIds)
            ->select('id', 'category')
            ->get()
            ->keyBy('id');

        $customerPaymentTotals = DB::table('customer_payments')
            ->select('bank_account_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('bank_account_id', $accountIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'customer_payments', $scope))
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');

        $supplierPaymentTotals = DB::table('supplier_payments')
            ->select('bank_account_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('bank_account_id', $accountIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'supplier_payments', $scope))
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');

        $paymentClearTotals = Schema::hasTable('payment_clears')
            ? DB::table('payment_clears')
                ->select('bank_account_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
                ->whereIn('bank_account_id', $accountIds)
                ->where('method', '!=', 'cash')
                ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'payment_clears', $scope))
                ->groupBy('bank_account_id')
                ->pluck('total', 'bank_account_id')
            : collect();

        $adjustmentTotals = DB::table('statement_adjustments')
            ->select('adjustable_id', DB::raw("COALESCE(SUM(CASE WHEN direction = 'minus' THEN -amount ELSE amount END), 0) as total"))
            ->where('adjustable_type', BankAccount::class)
            ->whereIn('adjustable_id', $accountIds)
            ->tap(fn ($query) => $this->applyBulkBalanceBranchScope($query, 'statement_adjustments', $scope))
            ->groupBy('adjustable_id')
            ->pluck('total', 'adjustable_id');

        return collect($accountIds)
            ->mapWithKeys(function ($accountId) use ($accounts, $customerPaymentTotals, $supplierPaymentTotals, $paymentClearTotals, $adjustmentTotals) {
                $category = $accounts[$accountId]->category ?? null;
                $adjustments = (float) ($adjustmentTotals[$accountId] ?? 0);
                $balance = $category === 'self'
                    ? (float) ($customerPaymentTotals[$accountId] ?? 0) - (float) ($supplierPaymentTotals[$accountId] ?? 0) + $adjustments
                    : (float) ($paymentClearTotals[$accountId] ?? 0) + $adjustments;

                return [$accountId => $balance];
            })
            ->all();
    }

    protected function balanceModuleKey(ModuleBranchService $branches): ?string
    {
        $moduleKey = $branches->currentModuleKey();

        if ($moduleKey && $branches->shouldFilterRecords($moduleKey)) {
            return $moduleKey;
        }

        try {
            $previousUrl = url()->previous();
            if (!$previousUrl) {
                return $moduleKey;
            }

            $previousRequest = request()->create($previousUrl);
            $previousRoute = app('router')->getRoutes()->match($previousRequest);
            $previousModuleKey = app(BranchModuleRegistryService::class)->moduleKeyForRoute($previousRoute);

            return $previousModuleKey ?: $moduleKey;
        } catch (\Throwable) {
            return $moduleKey;
        }
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
        $branches = app(ModuleBranchService::class);
        $workingModuleKey = $this->balanceModuleKey($branches);

        $employees = $branches->applyRelatedScope(
                Employee::where('category', $request->category)->where('status', 'active')->with('type'),
                'employees',
                $workingModuleKey,
            )
            ->whereHas('type', function ($query) {
                $query->where('title', 'not like', '% | E%');
            })
            ->get();

        $employees = collect($this->employeeOptionPayloads($employees, $workingModuleKey))->values();
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
            "statement_type" => "required|in:summarized,detailed,general",
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

    public function setPhysicalQuantityReportType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "physical_quantity_report_type" => "required|in:stock,altration",
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $user = Auth::user();
        $user->physical_quantity_report_type = $request->physical_quantity_report_type;
        $user->save();

        session()->flash('success', 'Physical quantity report type updated.');

        return response()->json([
            'status' => 'success',
            'message' => 'Physical quantity report type set as default.',
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

        $utilityAccounts = app(ModuleBranchService::class)
            ->applyRelatedScope(
                UtilityAccount::where('bill_type_id', $request->bill_type_id)->where('location_id', $request->location_id),
                'utility_accounts',
                'utility_bills'
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $utilityAccounts,
        ]);
    }
}
