<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceArticles;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderArticles;
use App\Models\Shipment;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Orders\OrderInvoiceSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;

use function PHPSTORM_META\type;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $invoices = app(ModuleBranchService::class)->applyScope(Invoice::with([
                'order',
                'shipment',
                'invoiceArticles.article',
                'salesReturns',
                'customer.city',
                'branch',
            ]), 'invoices')
            ->orderByDesc('id')
            ->applyFilters($request);


            return response()->json(['data' => $invoices, 'authLayout' => $authLayout]);
        }

        // $invoices = Invoice::with(['order.articles.article', 'shipment.articles.article', 'customer.city'])->orderBy('id', 'desc')->get();

        // foreach ($invoices as $invoice) {
        //     $articles = [];

        //     foreach ($invoice->articles_in_invoice as $article_in_invoice) {
        //         $article = Article::find($article_in_invoice['id']);

        //         $articles[] = [
        //             'article' => $article,
        //             'description' => $article_in_invoice['description'],
        //             'invoice_quantity' => $article_in_invoice['invoice_quantity'],
        //         ];
        //     }
        //     $invoice['articles'] = $articles;
        // }

        // return $invoices;
        return view('invoices.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $user = Auth::user();
        $orderNumber = session('orderNumber');
        $branches = app(ModuleBranchService::class);
        $invoiceTypeAvailability = [
            'order' => $branches->isClientModuleEnabled('orders'),
            'shipment' => $branches->isClientModuleEnabled('shipments'),
        ];
        $enabledInvoiceTypes = $branches->enabledWorkflowTypes([
            'order' => 'orders',
            'shipment' => 'shipments',
        ]);

        if ($orderNumber && $invoiceTypeAvailability['order']) {
            $user->invoice_type = 'order';
            $user->save();
        }

        $invoiceType = in_array($user?->invoice_type, ['order', 'shipment'], true)
            ? $user->invoice_type
            : 'order';
        if (!$invoiceTypeAvailability[$invoiceType]) {
            $invoiceType = $enabledInvoiceTypes[0] ?? 'manual';

            if ($invoiceType !== 'manual' && $user) {
                $user->invoice_type = $invoiceType;
                $user->save();
            }
        }
        $showInvoiceTypeSwitcher = collect($invoiceTypeAvailability)->filter()->count() > 1;

        $last_Invoice = Invoice::orderBy('id', 'desc')->first();

        if (!$last_Invoice) {
            $last_Invoice = new Invoice();
            $last_Invoice->invoice_no = '00-0000';
        }
        if (app(ModuleBranchService::class)->shouldFilterRecords('invoices')) {
            $last_Invoice = new Invoice();
            $last_Invoice->invoice_no = app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV');
        }
        $nextInvoiceNo = app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV');

        $customers = $branches->applyRelatedScope(Customer::with(['user', 'city']), 'customers', 'invoices')
            ->whereIn('category', ['regular', 'site'])->whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->get();

        $customerOptions = $customers
            ->mapWithKeys(fn (Customer $customer) => [
                $customer->id => ['text' => trim($customer->customer_name . ' | ' . ($customer->city?->title ?? '-'))],
            ])
            ->toArray();

        $ordersOptions = $invoiceTypeAvailability['order']
            ? $branches->applyRelatedScope(Order::query(), 'orders', 'invoices')
                ->where('status', '!=', 'invoiced')
                ->orderByDesc('id')
                ->pluck('order_no', 'order_no')
                ->map(fn ($orderNo) => ['text' => $orderNo])
                ->toArray()
            : [];

        $shipmentsOptions = $invoiceTypeAvailability['shipment']
            ? $branches->applyRelatedScope(Shipment::query(), 'shipments', 'invoices')
                ->orderByDesc('id')
                ->pluck('shipment_no', 'shipment_no')
                ->map(fn ($shipmentNo) => ['text' => $shipmentNo])
                ->toArray()
            : [];

        $branchBranding = app(ModuleBranchService::class)->documentBranding('invoices');

        return view("invoices.generate", compact("last_Invoice", 'customers', 'customerOptions', 'orderNumber', 'branchBranding', 'nextInvoiceNo', 'ordersOptions', 'shipmentsOptions', 'invoiceType', 'invoiceTypeAvailability', 'showInvoiceTypeSwitcher'));
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request)
    // {
    //     if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
    //     {
    //         return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
    //     };

    //     // check request has shipment no
    //     if ($request->has('shipment_no')) {
    //         $validator = Validator::make($request->all(), [
    //             "shipment_no" => "required|string|exists:shipments,shipment_no",
    //             "date" => "required|date",
    //             "customers_array" => "required|json",
    //             "printAfterSave" => "integer|in:0,1",
    //         ]);

    //         if ($validator->fails()) {
    //             return redirect()->back()->withErrors($validator)->withInput();
    //         }

    //         $customers_array = json_decode($request->customers_array, true);

    //         $shipment = Shipment::where("shipment_no", $request->shipment_no)->first();
    //         // $articlesInShipment = $shipment->getArticles();

    //         $last_Invoice = Invoice::orderBy('id', 'desc')->first();

    //         if (!$last_Invoice) {
    //             $last_Invoice = new Invoice();
    //             $last_Invoice->invoice_no = '00-0000';
    //         }

    //         $currentYear = date("y");

    //         $lastNumberPart = substr($last_Invoice->invoice_no, -4); // last 4 characters
    //         $nextNumber = str_pad((int)$lastNumberPart + 1, 4, '0', STR_PAD_LEFT);


    //         $invoiceNumbers = [];
    //         foreach ($customers_array as $customer) {
    //             // $article_in_invoice = [];
    //             // foreach ($articlesInShipment as $article) {
    //             //     $article_in_invoice[] = [
    //             //         "id" => $article['article']["id"],
    //             //         "description" => $article["description"],
    //             //         "invoice_quantity" => $article["shipment_quantity"] * $customer['carton_count'],
    //             //     ];
    //             //     $articleModel = Article::where("id", $article['article']["id"])->first();

    //             //     if ($articleModel) {
    //             //         $articleModel->increment('sold_quantity', $article["shipment_quantity"] * $customer['carton_count']);
    //             //         $articleModel->increment('ordered_quantity', $article["shipment_quantity"] * $customer['carton_count']);
    //             //     }
    //             // }

    //             $invoice = new Invoice();
    //             $invoice->customer_id = $customer["id"];
    //             $invoice->invoice_no = $currentYear . '-' . $nextNumber;
    //             $invoice->shipment_no = $request->shipment_no;
    //             $invoice->netAmount = $shipment->netAmount * $customer['carton_count'];
    //             $invoice->carton_count = $customer['carton_count'];
    //             // $invoice->articles_in_invoice = $article_in_invoice;
    //             $invoice->date = date("Y-m-d");

    //             $nextNumber = str_pad((int)$nextNumber + 1, 4, '0', STR_PAD_LEFT);

    //             $invoiceNumbers[] = $currentYear . '-' . str_pad((int)$nextNumber - 1, 4, '0', STR_PAD_LEFT);

    //             $invoice->save();
    //         }

    //         if ($request->printAfterSave) {
    //             return redirect()->route('invoices.print')->with('invoiceNumbers', $invoiceNumbers);
    //         } else {
    //             return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
    //         }
    //     }
    //     else if ($request->has('order_no')) {
    //         $validator = Validator::make($request->all(), [
    //             "invoice_no" => "required|string|unique:invoices,invoice_no",
    //             "order_no" => "required|string|exists:orders,order_no",
    //             "date" => "required|date",
    //             "netAmount" => "required|string",
    //             "articles_in_invoice" => "required|string",
    //         ]);

    //         if ($validator->fails()) {
    //             return redirect()->back()->withErrors($validator)->withInput();
    //         }

    //         $data = $request->all();

    //         $data['articles_in_invoice'] = json_decode($data['articles_in_invoice'], true);

    //         // return $data;

    //         // foreach ($data['articles_in_invoice'] as $article) {
    //         //     $articleDb = Article::where("id", $article["id"])->increment('sold_quantity', $article["invoice_quantity"]);
    //         // }

    //         $orderDb = Order::where("order_no", $data["order_no"])->first();
    //         foreach ($data['articles_in_invoice'] as $article) {
    //             // $orderedArticleDb = json_decode($orderDb["articles"], true);

    //             // Update all matching articles
    //             // foreach ($orderedArticleDb as &$orderedArticle) { // Pass by reference!
    //             //     if (isset($orderedArticle["id"]) && $orderedArticle["id"] == $article["id"]) {
    //             //         $orderedArticle["invoice_quantity"] = ($orderedArticle["invoice_quantity"] ?? 0) + $article["invoice_quantity"];
    //             //     }
    //             // }
    //             // unset($orderedArticle); // Important: break reference after loop

    //             // Save updated articles back to the database
    //             // $orderDb->articles = json_encode($orderedArticleDb);

    //             $orderArticleDb = OrderArticles::find($article['order_article_id']);
    //             $orderArticleDb->dispatched_pcs = $article['invoice_quantity'];
    //             $orderArticleDb->save();

    //             if ($orderArticleDb->dispatched_pcs == 0) {
    //                 $orderDb->status = 'pending';
    //             } elseif ($orderArticleDb->dispatched_pcs < $orderArticleDb->ordered_pcs) {
    //                 $orderDb->status = 'partially_invoiced';
    //             } else {
    //                 $orderDb->status = 'invoiced';
    //             }

    //             $orderDb->save();
    //         }

    //         $data["netAmount"] = (int) str_replace(',', '', $data["netAmount"]);
    //         $data["customer_id"] = $orderDb["customer_id"];

    //         Invoice::create($data);
    //     }

    //     return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
    // }

    // public function store(Request $request)
    // {
    //     if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
    //         return $resp;
    //     }

    //     if ($request->has('order_no')) {
    //         $validator = Validator::make($request->all(), [
    //             "invoice_no" => "required|string",
    //             "order_no" => "required|string",
    //             "date" => "required|date",
    //             "netAmount" => "required|string",
    //             "articles_in_invoice" => "required|string",
    //         ]);

    //         if ($validator->fails()) {
    //             return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
    //         }

    //         $invoice = null;
    //         $invoiceCustomerId = null;
    //         $data = [
    //             'invoice_no' => app(ModuleBranchService::class)->shouldFilterRecords('invoices')
    //                 ? app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV')
    //                 : $request->invoice_no,
    //             'order_no' => $request->order_no,
    //             'date' => $request->date,
    //             'netAmount' => $request->netAmount,
    //             'articles_in_invoice' => json_decode($request->articles_in_invoice, true),
    //         ];

    //         DB::transaction(function () use ($data, &$invoice, &$invoiceCustomerId) {
    //             $branches = app(ModuleBranchService::class);
    //             $orderQuery = $branches->applyRelatedScope(Order::with('articles.article'), 'orders', 'invoices');
    //             $orderDb = $this->applyDocumentNumberLookup($orderQuery, 'order_no', $data["order_no"])->lockForUpdate()->first();
    //             if (!$orderDb) {
    //                 throw ValidationException::withMessages([
    //                     'order_no' => 'Order not found for the selected branch.',
    //                 ]);
    //             }

    //             $sync = app(OrderInvoiceSyncService::class);
    //             $lines = $sync->normalizeInvoiceLines($data['articles_in_invoice']);
    //             $sync->validateInvoiceAgainstOrder($orderDb, $lines);
    //             $this->validateInvoiceStock($lines->groupBy('article_id')->map(fn ($group) => $group->sum('invoice_pcs')), $orderDb->id);

    //             $invoice = Invoice::create([
    //                 'invoice_no' => $data['invoice_no'],
    //                 'order_no' => $orderDb->order_no,
    //                 'shipment_no' => null,
    //                 'date' => $data['date'],
    //                 'netAmount' => (int) str_replace(',', '', $data['netAmount']),
    //                 'customer_id' => $orderDb->customer_id,
    //                 'branch_id' => $orderDb->branch_id ?: $branches->branchIdForCreate('invoices'),
    //             ]);
    //             $invoiceCustomerId = (int) $orderDb->customer_id;

    //             $sync->replaceInvoiceArticles($invoice, $lines);
    //             $sync->recalculateOrderDispatch($orderDb);
    //         });

    //         if ($invoice && $invoiceCustomerId) {
    //             $this->notifyCustomerAboutInvoice($invoiceCustomerId, $invoice->invoice_no);
    //         }
    //     } else {
    //         return redirect()->back()->withInput()->with('error', 'Invoice must be created against an order.');
    //     }

    //     return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
    // }

    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole([
            'developer',
            'owner',
            'admin',
            'accountant',
        ])) {
            return $resp;
        }

        $invoiceNumbers = [];
        $branches = app(ModuleBranchService::class);

        /*
        |--------------------------------------------------------------------------
        | ORDER INVOICE
        |--------------------------------------------------------------------------
        */
        if ($request->filled('order_no')) {
            if (!$branches->isClientModuleEnabled('orders')) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Order invoices are disabled because Orders module is disabled.');
            }

            $validator = Validator::make($request->all(), [
                'invoice_no' => 'required|string',
                'order_no' => 'required|string',
                'date' => 'required|date',
                'netAmount' => 'required|string',
                'articles_in_invoice' => 'required|string',
            ]);

            if ($validator->fails()) {
                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', $validator->errors()->first());
            }

            $invoice = null;
            $invoiceCustomerId = null;

            $data = [
                'invoice_no' => app(ModuleBranchService::class)->shouldFilterRecords('invoices')
                    ? app(BranchSerialService::class)->next(
                        'invoices',
                        Invoice::class,
                        'invoice_no',
                        'INV'
                    )
                    : $request->invoice_no,

                'order_no' => $request->order_no,
                'date' => $request->date,
                'netAmount' => $request->netAmount,
                'articles_in_invoice' => json_decode(
                    $request->articles_in_invoice,
                    true
                ),
            ];

            DB::transaction(function () use (
                $data,
                &$invoice,
                &$invoiceCustomerId
            ) {
                $branches = app(ModuleBranchService::class);

                $orderQuery = $branches->applyRelatedScope(
                    Order::with('articles.article'),
                    'orders',
                    'invoices'
                );

                $orderDb = $this->applyDocumentNumberLookup(
                    $orderQuery,
                    'order_no',
                    $data['order_no']
                )
                    ->lockForUpdate()
                    ->first();

                if (!$orderDb) {
                    throw ValidationException::withMessages([
                        'order_no' => 'Order not found for the selected branch.',
                    ]);
                }

                $sync = app(OrderInvoiceSyncService::class);

                $lines = $sync->normalizeInvoiceLines(
                    $data['articles_in_invoice']
                );

                $sync->validateInvoiceAgainstOrder(
                    $orderDb,
                    $lines
                );

                $this->validateInvoiceStock(
                    $lines->groupBy('article_id')->map(
                        fn ($group) => $group->sum('invoice_pcs')
                    ),
                    $orderDb->id
                );

                $invoice = Invoice::create([
                    'invoice_no' => $data['invoice_no'],
                    'order_no' => $orderDb->order_no,
                    'shipment_no' => null,
                    'date' => $data['date'],
                    'netAmount' => (int) str_replace(
                        ',',
                        '',
                        $data['netAmount']
                    ),
                    'customer_id' => $orderDb->customer_id,
                    'branch_id' => $orderDb->branch_id
                        ?: $branches->branchIdForCreate('invoices'),
                ]);

                $invoiceCustomerId = (int) $orderDb->customer_id;

                $sync->replaceInvoiceArticles(
                    $invoice,
                    $lines
                );

                $sync->recalculateOrderDispatch(
                    $orderDb
                );
            });

            if ($invoice && $invoiceCustomerId) {
                $this->notifyCustomerAboutInvoice(
                    $invoiceCustomerId,
                    $invoice->invoice_no
                );
            }

            if ($request->boolean('printAfterSave')) {
                session()->put('invoiceNumbers', [$invoice->invoice_no]);

                return redirect()->route('invoices.print');
            }

            return redirect()
                ->route('invoices.create')
                ->with(
                    'success',
                    'Invoice generated successfully.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | SHIPMENT INVOICE
        |--------------------------------------------------------------------------
        */
        if ($request->filled('shipment_no')) {
            if (!$branches->isClientModuleEnabled('shipments')) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Shipment invoices are disabled because Shipments module is disabled.');
            }

            $validator = Validator::make($request->all(), [
                'shipment_no' => 'required|string',
                'date' => 'required|date',
                'netAmount' => 'required|string',
                'customers_array' => 'required|string',
            ]);

            if ($validator->fails()) {
                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', $validator->errors()->first());
            }

            $customers = json_decode(
                $request->customers_array,
                true
            );

            if (!is_array($customers) || empty($customers)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with(
                        'error',
                        'Please select at least one customer.'
                    );
            }

            /*
            * Validate selected customers.
            */
            foreach ($customers as $customer) {
                if (
                    !isset($customer['id']) ||
                    !isset($customer['carton_count'])
                ) {
                    return redirect()
                        ->back()
                        ->withInput()
                        ->with(
                            'error',
                            'Invalid customer selection data.'
                        );
                }

                if ((int) $customer['carton_count'] < 1) {
                    return redirect()
                        ->back()
                        ->withInput()
                        ->with(
                            'error',
                            'Carton count must be at least 1.'
                        );
                }
            }

            $createdInvoices = [];
            $customerIds = [];

            DB::transaction(function () use (
                $request,
                $customers,
                &$createdInvoices,
                &$customerIds,
                &$invoiceNumbers
            ) {

                $branches = app(ModuleBranchService::class);

                /*
                * Get shipment with its articles.
                *
                * This follows the same relationship style already used
                * in your existing order invoice code:
                *
                * Order::with('articles.article')
                *
                * If your Shipment relationship has another name,
                * change 'articles' here.
                */
                $shipmentQuery = $branches->applyRelatedScope(
                    Shipment::with('articles.article'),
                    'shipments',
                    'invoices'
                );

                $shipment = $this->applyDocumentNumberLookup(
                    $shipmentQuery,
                    'shipment_no',
                    $request->shipment_no
                )
                    ->lockForUpdate()
                    ->first();

                if (!$shipment) {
                    throw ValidationException::withMessages([
                        'shipment_no' =>
                            'Shipment not found for the selected branch.',
                    ]);
                }

                /*
                * Shipment must have articles.
                */
                if (
                    !$shipment->articles ||
                    $shipment->articles->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'shipment_no' =>
                            'The selected shipment has no articles.',
                    ]);
                }

                /*
                * Make sure selected customers exist.
                */
                $selectedCustomerIds = collect($customers)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $customerModels = Customer::whereIn(
                    'id',
                    $selectedCustomerIds
                )
                    ->get()
                    ->keyBy('id');

                if (
                    $customerModels->count() !==
                    $selectedCustomerIds->count()
                ) {
                    throw ValidationException::withMessages([
                        'customers_array' =>
                            'One or more selected customers were not found.',
                    ]);
                }

                /*
                * Calculate total cartons.
                */
                $totalCartons = collect($customers)->sum(
                    fn ($customer) => (int) $customer['carton_count']
                );

                if ($totalCartons <= 0) {
                    throw ValidationException::withMessages([
                        'customers_array' =>
                            'Invalid carton count.',
                    ]);
                }

                /*
                * Create one invoice per selected customer.
                */
                foreach ($customers as $customerData) {

                    $customerId = (int) $customerData['id'];
                    $cartonCount = (int) $customerData['carton_count'];

                    $customer = $customerModels->get($customerId);

                    if (!$customer) {
                        continue;
                    }

                    /*
                    * Generate invoice number.
                    */
                    $invoiceNo = $branches->shouldFilterRecords('invoices')
                        ? app(BranchSerialService::class)->next(
                            'invoices',
                            Invoice::class,
                            'invoice_no',
                            'INV'
                        )
                        : $request->invoice_no;

                    /*
                    * Calculate invoice amount.
                    *
                    * shipment_pcs is multiplied by customer's carton count.
                    */

                    foreach ($shipment->articles as $shipmentArticle) {

                        $shipmentPcs = (int) (
                            $shipmentArticle->shipment_pcs
                            ?? $shipmentArticle->quantity
                            ?? 0
                        );

                        $salesRate = (float) (
                            $shipmentArticle->article->sales_rate
                            ?? 0
                        );
                    }

                    /*
                    * Create invoice.
                    */
                    $invoice = Invoice::create([
                        'invoice_no' => $invoiceNo,
                        'order_no' => null,
                        'shipment_no' => $shipment->shipment_no,
                        'date' => $request->date,
                        'netAmount' => (int) str_replace(
                            ',',
                            '',
                            $request->netAmount
                        ) * $cartonCount,
                        'customer_id' => $customerId,
                        'branch_id' => $shipment->branch_id
                            ?: $branches->branchIdForCreate('invoices'),
                        'carton_count' => $cartonCount,
                    ]);

                    $invoiceNumbers[] = $invoice->invoice_no;

                    /*
                    * Create invoice article rows.
                    */
                    foreach ($shipment->articles as $shipmentArticle) {

                        $articleId = $shipmentArticle->article_id
                            ?? $shipmentArticle->article?->id;

                        if (!$articleId) {
                            continue;
                        }

                        $shipmentPcs = (int) (
                            $shipmentArticle->shipment_pcs
                            ?? $shipmentArticle->quantity
                            ?? 0
                        );

                        $invoicePcs =
                            $shipmentPcs * $cartonCount;

                        InvoiceArticles::create([
                            'invoice_id' => $invoice->id,
                            'article_id' => $articleId,
                            'description' =>
                                $shipmentArticle->description ?? '',
                            'invoice_pcs' => $invoicePcs,
                        ]);
                    }

                    $createdInvoices[] = $invoice;
                    $customerIds[] = $customerId;
                }
            });

            /*
            * Notify customers after successful transaction.
            */
            foreach ($createdInvoices as $index => $invoice) {
                $customerId = $customerIds[$index] ?? null;

                if ($customerId) {
                    $this->notifyCustomerAboutInvoice(
                        $customerId,
                        $invoice->invoice_no
                    );
                }
            }

            if ($request->boolean('printAfterSave')) {
                session()->put('invoiceNumbers', $invoiceNumbers);

                return redirect()->route('invoices.print');
            }

            return redirect()
                ->route('invoices.create')
                ->with(
                    'success',
                    count($createdInvoices) .
                    ' shipment invoice(s) generated successfully.'
                );
        }

        if (!$branches->isClientModuleEnabled('orders') && !$branches->isClientModuleEnabled('shipments')) {
            $validator = Validator::make($request->all(), [
                'invoice_no' => 'required|string',
                'customer_id' => 'required|integer|exists:customers,id',
                'date' => 'required|date',
                'netAmount' => 'required|string',
            ]);

            if ($validator->fails()) {
                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', $validator->errors()->first());
            }

            $invoice = Invoice::create([
                'invoice_no' => $branches->shouldFilterRecords('invoices')
                    ? app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV')
                    : $request->invoice_no,
                'order_no' => null,
                'shipment_no' => null,
                'date' => $request->date,
                'netAmount' => (int) str_replace(',', '', $request->netAmount),
                'customer_id' => (int) $request->customer_id,
                'branch_id' => $branches->branchIdForCreate('invoices'),
            ]);

            $this->notifyCustomerAboutInvoice((int) $request->customer_id, $invoice->invoice_no);

            if ($request->boolean('printAfterSave')) {
                session()->put('invoiceNumbers', [$invoice->invoice_no]);

                return redirect()->route('invoices.print');
            }

            return redirect()
                ->route('invoices.create')
                ->with('success', 'Manual invoice generated successfully.');
        }

        /*
        |--------------------------------------------------------------------------
        | INVALID REQUEST
        |--------------------------------------------------------------------------
        */
        return redirect()
            ->back()
            ->withInput()
            ->with(
                'error',
                'Invoice must be created against an order or shipment.'
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(invoice $invoice)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(invoice $invoice)
    {
        if (
            !$this->checkRole(['developer', 'owner', 'admin', 'accountant']) &&
            !app_can('orders', 'override')
        ) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');
    
        $invoice->load(['customer.city', 'invoiceArticles.article', 'order', 'shipment.articles.article', 'creator']);
    
        // Detect the real invoice type from what's actually stored on the record,
        // instead of assuming "order" like the old code did.
        $invoiceType = $invoice->shipment_no ? 'shipment' : 'order';
        $isDeveloper = Auth::user()?->role === 'developer' || app_can('orders', 'override');
    
        $branches = app(ModuleBranchService::class);
    
        $customers = $branches->applyRelatedScope(Customer::with('city'), 'customers', 'invoices')
            ->orderBy('customer_name')
            ->get()
            ->mapWithKeys(fn (Customer $customer) => [
                $customer->id => [
                    'text' => trim($customer->customer_name . ' | ' . ($customer->city?->title ?? '-')),
                    'data_option' => [
                        'id' => $customer->id,
                        'customer_name' => $customer->customer_name,
                        'city' => $customer->city ? [
                            'id' => $customer->city->id,
                            'title' => $customer->city->title,
                            'short_title' => $customer->city->short_title,
                        ] : null,
                    ],
                ],
            ])
            ->all();
    
        $ordersOptions = [];
        $shipmentsOptions = [];
        $articles = [];
    
        if ($invoiceType === 'order') {
            $ordersOptions = $branches->applyRelatedScope(Order::query(), 'orders', 'invoices')
                ->where(function ($query) use ($invoice) {
                    $query->where('status', '!=', 'invoiced');
    
                    if ($invoice->order_no) {
                        $query->orWhere('order_no', $invoice->order_no);
                    }
                })
                ->orderByDesc('id')
                ->pluck('order_no', 'order_no')
                ->map(fn ($orderNo) => ['text' => $orderNo])
                ->toArray();
    
            $articleIds = $invoice->order?->articles?->pluck('article_id')
                ->merge($invoice->invoiceArticles->pluck('article_id'))
                ->unique()
                ->values() ?? collect();
    
            $articles = Article::whereIn('id', $articleIds)
                ->orderBy('article_no')
                ->get()
                ->keyBy('id')
                ->map(fn (Article $article) => [
                    'text' => trim($article->article_no . ' | ' . ($article->description ?? '')),
                    'data_option' => [
                        'id' => $article->id,
                        'article_no' => $article->article_no,
                        'description' => $article->description,
                        'fabric_type' => $article->fabric_type,
                        'pcs_per_packet' => $article->pcs_per_packet,
                        'sales_rate' => $article->sales_rate,
                    ],
                ])
                ->all();
        } else {
            // Shipment invoice: only offer shipments that are still open for
            // invoicing, or the invoice's own current shipment.
            $shipmentsOptions = $branches->applyRelatedScope(Shipment::query(), 'shipments', 'invoices')
                ->where(function ($query) use ($invoice) {
                    $query->orWhere('shipment_no', $invoice->shipment_no);
                })
                ->orderByDesc('id')
                ->pluck('shipment_no', 'shipment_no')
                ->map(fn ($shipmentNo) => ['text' => $shipmentNo])
                ->toArray();
        }
    
        $branchBranding = $branches->documentBranding('invoices');
    
        return view('invoices.edit', compact(
            'invoice',
            'customers',
            'articles',
            'branchBranding',
            'ordersOptions',
            'shipmentsOptions',
            'invoiceType',
            'isDeveloper'
        ));
    }
    
    /**
     * Update the specified resource in storage.
     *
     * Dispatches to the correct type-specific updater based on the invoice's
     * current type, since order and shipment invoices have different fields
     * and recalculation logic.
     */
    public function update(Request $request, invoice $invoice)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');
    
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
    
        $dependencies = $this->dependencyCounts([
            'bilty' => ['bilties', 'invoice_id', $invoice->id],
            'sales returns' => ['sales_returns', 'invoice_id', $invoice->id],
            'cargo lists' => function () use ($invoice) {
                return DB::table('cargos')
                    ->where('invoices_array', 'like', '%"id":' . $invoice->id . '%')
                    ->orWhere('invoices_array', 'like', '%"id":"' . $invoice->id . '"%')
                    ->count();
            },
        ]);
    
        if (!empty($dependencies)) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->dependencyBlockMessage('Invoice', $dependencies));
        }
    
        $isDeveloper = Auth::user()?->role === 'developer' || app_can('invoices', 'override');;
        $invoiceType = $invoice->shipment_no ? 'shipment' : 'order';
    
        return $invoiceType === 'order'
            ? $this->updateOrderInvoice($request, $invoice, $isDeveloper)
            : $this->updateShipmentInvoice($request, $invoice, $isDeveloper);
    }
    
    /**
     * Update an order-based invoice.
     *
     * Only a developer may change invoice_no, order_no, or customer_id.
     * Every other allowed role can only edit date, articles/quantities, and
     * carton_count.
     */
    protected function updateOrderInvoice(Request $request, invoice $invoice, bool $isDeveloper)
    {
        $rawArticles = json_decode((string) $request->input('articles_in_invoice'), true) ?: [];

        $request->merge([
            'articles' => array_map(fn ($row) => [
                'id'          => $row['invoice_article_id'] ?? null, // invoice_articles PK (existing line)
                'article_id'  => $row['id'] ?? null,                 // JS's "id" is actually the article id
                'description' => $row['description'] ?? null,
                'invoice_pcs' => $row['invoice_quantity'] ?? null,
            ], $rawArticles),
        ]);

        $rules = [
            'date' => ['required', 'date'],
            'netAmount' => ['required'],
            'carton_count' => ['nullable', 'integer', 'min:0'],
            'articles' => ['required', 'array', 'min:1'],
            'articles.*.id' => ['nullable', 'integer', 'exists:invoice_articles,id'],
            'articles.*.article_id' => ['required', 'integer', 'exists:articles,id'],
            'articles.*.description' => ['nullable', 'string', 'max:255'],
            'articles.*.invoice_pcs' => ['required', 'integer', 'min:1'],
        ];
    
        if ($isDeveloper) {
            $rules['invoice_no'] = ['required', 'string', 'max:255'];
            $rules['order_no'] = ['required', 'string', 'max:255'];
            $rules['customer_id'] = ['nullable', 'integer', 'exists:customers,id'];
        }
    
        $validated = $request->validate($rules);

        try {
            DB::transaction(function () use ($invoice, $validated, $isDeveloper) {
                $previousOrderNo = $invoice->order_no;
                $branches = app(ModuleBranchService::class);
        
                // Non-developers can't change which order the invoice is tied to.
                $orderNo = $isDeveloper
                    ? ($validated['order_no'] ?? $invoice->order_no)
                    : $invoice->order_no;
        
                $orderQuery = $branches->applyRelatedScope(Order::with('articles.article'), 'orders', 'invoices');
                $order = $this->applyDocumentNumberLookup($orderQuery, 'order_no', $orderNo)
                    ->lockForUpdate()
                    ->first();
        
                if (!$order) {
                    throw ValidationException::withMessages([
                        'order_no' => 'Order not found for the selected branch.',
                    ]);
                }
        
                $sync = app(OrderInvoiceSyncService::class);
                $lines = $sync->normalizeInvoiceLines($validated['articles']);
                $sync->validateInvoiceAgainstOrder($order, $lines, $invoice);
                $this->validateInvoiceStock(
                    $lines->groupBy('article_id')->map(
                        fn ($group) => $group->sum('invoice_pcs')
                    ),
                    $order->id,
                    $invoice
                );
        
                $updateData = [
                    'order_no' => $order->order_no,
                    'shipment_no' => null,
                    'customer_id' => $order->customer_id,
                    'date' => $validated['date'],
                    'netAmount' => (int) str_replace(',', '', (string) $validated['netAmount']),
                    'carton_count' => $validated['carton_count'] ?? null,
                    'branch_id' => $order->branch_id ?: $branches->branchIdForCreate('invoices'),
                ];
        
                if ($isDeveloper) {
                    $updateData['invoice_no'] = $validated['invoice_no'];
        
                    // Developer can override the customer independently of the
                    // order's own customer, if they explicitly chose one.
                    if (!empty($validated['customer_id'])) {
                        $updateData['customer_id'] = $validated['customer_id'];
                    }
                }
        
                $invoice->update($updateData);
        
                $sync->replaceInvoiceArticles($invoice, $lines);
                $sync->recalculateOrderDispatch($order);
        
                if ($previousOrderNo && $previousOrderNo !== $order->order_no) {
                    $sync->recalculateOrderDispatch($previousOrderNo);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    
        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
    }
    
    /**
     * Update a shipment-based invoice.
     *
     * Only a developer may change invoice_no, shipment_no, or customer_id.
     * Every other allowed role can only edit date, netAmount, and carton_count
     * (which recalculates the invoice article quantities off the shipment's
     * per-carton rates).
     */
    protected function updateShipmentInvoice(Request $request, invoice $invoice, bool $isDeveloper)
    {
        $rules = [
            'date' => ['required', 'date'],
            'netAmount' => ['required'],
            'carton_count' => ['required', 'integer', 'min:1'],
        ];
    
        if ($isDeveloper) {
            $rules['invoice_no'] = ['required', 'string', 'max:255'];
            $rules['shipment_no'] = ['required', 'string', 'max:255'];
            $rules['customer_id'] = ['required', 'integer', 'exists:customers,id'];
        }
    
        $validated = $request->validate($rules);
    
        DB::transaction(function () use ($invoice, $validated, $isDeveloper) {
            $branches = app(ModuleBranchService::class);
    
            $shipmentNo = $isDeveloper
                ? ($validated['shipment_no'] ?? $invoice->shipment_no)
                : $invoice->shipment_no;
    
            $shipmentQuery = $branches->applyRelatedScope(Shipment::with('articles.article'), 'shipments', 'invoices');
            $shipment = $this->applyDocumentNumberLookup($shipmentQuery, 'shipment_no', $shipmentNo)
                ->lockForUpdate()
                ->first();
    
            if (!$shipment) {
                throw ValidationException::withMessages([
                    'shipment_no' => 'Shipment not found for the selected branch.',
                ]);
            }
    
            $cartonCount = (int) $validated['carton_count'];
    
            $updateData = [
                'shipment_no' => $shipment->shipment_no,
                'order_no' => null,
                'date' => $validated['date'],
                'netAmount' => (int) str_replace(',', '', (string) $validated['netAmount']),
                'carton_count' => $cartonCount,
                'branch_id' => $shipment->branch_id ?: $branches->branchIdForCreate('invoices'),
            ];
    
            if ($isDeveloper) {
                $updateData['invoice_no'] = $validated['invoice_no'];
                $updateData['customer_id'] = $validated['customer_id'];
            }
    
            $invoice->update($updateData);
    
            // Rebuild invoice article lines from the shipment's current article
            // rates, scaled by the (possibly changed) carton count.
            $invoice->invoiceArticles()->delete();
    
            foreach ($shipment->articles as $shipmentArticle) {
                $articleId = $shipmentArticle->article_id ?? $shipmentArticle->article?->id;
    
                if (!$articleId) {
                    continue;
                }
    
                $shipmentPcs = (int) (
                    $shipmentArticle->shipment_pcs
                    ?? $shipmentArticle->quantity
                    ?? 0
                );
    
                InvoiceArticles::create([
                    'invoice_id' => $invoice->id,
                    'article_id' => $articleId,
                    'description' => $shipmentArticle->description ?? '',
                    'invoice_pcs' => $shipmentPcs * $cartonCount,
                ]);
            }
        });
    
        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(invoice $invoice)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $dependencies = $this->dependencyCounts([
            'bilty' => ['bilties', 'invoice_id', $invoice->id],
            'sales returns' => ['sales_returns', 'invoice_id', $invoice->id],
            'cargo lists' => function () use ($invoice) {
                return DB::table('cargos')
                    ->where('invoices_array', 'like', '%"id":' . $invoice->id . '%')
                    ->orWhere('invoices_array', 'like', '%"id":"' . $invoice->id . '"%')
                    ->count();
            },
        ]);

        if (!empty($dependencies)) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Invoice', $dependencies));
        }

        DB::transaction(function () use ($invoice) {
            $orderNo = $invoice->order_no;
            $invoice->invoiceArticles()->delete();
            $invoice->delete();
            app(OrderInvoiceSyncService::class)->recalculateOrderDispatch($orderNo);
        });

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function print()
    {
        /*
        |--------------------------------------------------------------------------
        | Get invoice numbers saved by store()
        |--------------------------------------------------------------------------
        */
        $invoiceNumbers = session()->get('invoiceNumbers', []);

        if (!is_array($invoiceNumbers)) {
            $invoiceNumbers = [$invoiceNumbers];
        }

        $invoiceNumbers = collect($invoiceNumbers)
            ->filter(fn ($number) => filled($number))
            ->map(fn ($number) => trim((string) $number))
            ->unique()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | No invoice numbers
        |--------------------------------------------------------------------------
        */
        if (empty($invoiceNumbers)) {
            return redirect()
                ->route('invoices.create')
                ->with('error', 'No invoices to print.');
        }

        /*
        |--------------------------------------------------------------------------
        | Load invoices
        |--------------------------------------------------------------------------
        */
        $invoices = Invoice::query()
            ->with([
                'customer.city',
                'invoiceArticles.article',
                'shipment',
                'order',
                'branch',
            ])
            ->whereIn('invoice_no', $invoiceNumbers)
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Invoice numbers exist but records were not found
        |--------------------------------------------------------------------------
        */
        if ($invoices->isEmpty()) {
            // Clear invalid print session
            session()->forget('invoiceNumbers');

            return redirect()
                ->route('invoices.create')
                ->with(
                    'error',
                    'Invoices not found: ' . implode(', ', $invoiceNumbers)
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare invoice payloads
        |--------------------------------------------------------------------------
        */
        $invoicePayloads = $invoices
            ->map(function (Invoice $invoice) {
                $formatted = $invoice->toFormattedArray();

                return $formatted['data'] ?? $formatted;
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Remove print queue after successfully loading invoices
        |--------------------------------------------------------------------------
        */
        session()->forget('invoiceNumbers');

        /*
        |--------------------------------------------------------------------------
        | Print view
        |--------------------------------------------------------------------------
        */
        return view('invoices.print', [
            'invoices' => $invoices,
            'invoicePayloads' => $invoicePayloads,
            'client_company' => app('client_company'),
        ]);
    }

    protected function validateInvoiceStock(
    Collection $requestedQuantities,
    int $orderId,
    ?Invoice $editingInvoice = null
): void {
    $order = Order::with('articles')->find($orderId);

    if (!$order) {
        throw ValidationException::withMessages([
            'articles' => 'Order not found while validating invoice quantities.',
        ]);
    }

    /*
     * Existing invoice quantities belonging to THIS invoice.
     *
     * When editing an invoice, these quantities are already included
     * in the order's dispatched quantity. We temporarily release them
     * so the same quantity can be saved again.
     */
    $oldInvoiceQuantities = collect();

    if ($editingInvoice) {
        $oldInvoiceQuantities = $editingInvoice
            ->invoiceArticles()
            ->select('article_id', DB::raw('SUM(invoice_pcs) as total'))
            ->groupBy('article_id')
            ->pluck('total', 'article_id')
            ->map(fn ($qty) => (int) $qty);
    }

    /*
     * Build order quantities indexed by article_id.
     */
    $orderQuantities = $order->articles
        ->groupBy('article_id')
        ->map(function ($articles) {
            return [
                'ordered' => (int) $articles->sum('ordered_pcs'),
                'dispatched' => (int) $articles->sum('dispatched_pcs'),
            ];
        });

    foreach ($requestedQuantities as $articleId => $requestedQty) {
        $articleId = (int) $articleId;
        $requestedQty = (int) $requestedQty;

        $orderArticle = $orderQuantities->get($articleId);

        if (!$orderArticle) {
            throw ValidationException::withMessages([
                'articles' => "Article {$articleId} does not belong to this order.",
            ]);
        }

        $orderedQty = $orderArticle['ordered'];
        $dispatchedQty = $orderArticle['dispatched'];

        /*
         * Release the current invoice's old quantity.
         *
         * Example:
         *
         * Ordered     = 100
         * Dispatched  = 100
         * Current INV = 100
         *
         * Real available for editing:
         * 100 - (100 - 100) = 100
         */
        $oldInvoiceQty = (int) $oldInvoiceQuantities->get($articleId, 0);

        $availableForThisInvoice = max(
            0,
            $orderedQty - max(0, $dispatchedQty - $oldInvoiceQty)
        );

        if ($requestedQty > $availableForThisInvoice) {
            throw ValidationException::withMessages([
                'articles' => sprintf(
                    'Article %s has only %s quantity available. You are trying to invoice %s.',
                    $articleId,
                    $availableForThisInvoice,
                    $requestedQty
                ),
            ]);
        }
    }
}

    private function notifyCustomerAboutInvoice(int $customerId, string $invoiceNo): void
    {
        try {
            $customer = Customer::with('user')->find($customerId);
            $receiverId = $customer?->user?->id;

            if (!$receiverId || $customer?->user?->status !== 'active') {
                return;
            }

            $notificationPayload = [
                'title' => 'Invoice Created',
                'message' => "Aap ke liye invoice {$invoiceNo} create ho gaya hai.",
                'type' => 'info',
                'persist' => true,
                'target_user_ids' => [$receiverId],
            ];
            $storedNotificationPayload = [
                't' => 'Invoice Created',
                'm' => "Aap ke liye invoice {$invoiceNo} create ho gaya hai.",
                'tp' => 'info',
                'p' => true,
                'tu' => [$receiverId],
            ];

            Notification::create([
                'senderId' => Auth::id(),
                'recieverId' => $receiverId,
                'caption' => json_encode($storedNotificationPayload),
            ]);

            event(new NewNotificationEvent($notificationPayload));
        } catch (\Throwable $e) {
            Log::error('Invoice customer notification failed', [
                'invoice_no' => $invoiceNo,
                'customer_id' => $customerId,
                'auth_user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);
        }
    }

}
