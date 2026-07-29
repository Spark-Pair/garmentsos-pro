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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

        $orderNumber = session('orderNumber');

        if ($orderNumber) {
            $user = Auth::user();
            $user->invoice_type = 'order';
            $user->save();
        }

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

        $branches = app(ModuleBranchService::class);
        $customers = $branches->applyRelatedScope(Customer::with('user'), 'customers', 'invoices')
            ->whereIn('category', ['regular', 'site'])->whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->get();

        $ordersOptions = $branches->applyRelatedScope(Order::query(), 'orders', 'invoices')
            ->where('status', '!=', 'invoiced')
            ->orderByDesc('id')
            ->pluck('order_no', 'order_no')
            ->map(fn ($orderNo) => ['text' => $orderNo])
            ->toArray();

        $shipmentsOptions = $branches->applyRelatedScope(Shipment::query(), 'shipments', 'invoices')
            ->orderByDesc('id')
            ->pluck('shipment_no', 'shipment_no')
            ->map(fn ($shipmentNo) => ['text' => $shipmentNo])
            ->toArray();

        $branchBranding = app(ModuleBranchService::class)->documentBranding('invoices');

        return view("invoices.generate", compact("last_Invoice", 'customers', 'orderNumber', 'branchBranding', 'nextInvoiceNo', 'ordersOptions', 'shipmentsOptions'));
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
    //             //         "invoice_quantity" => $article["shipment_quantity"] * $customer['cotton_count'],
    //             //     ];
    //             //     $articleModel = Article::where("id", $article['article']["id"])->first();

    //             //     if ($articleModel) {
    //             //         $articleModel->increment('sold_quantity', $article["shipment_quantity"] * $customer['cotton_count']);
    //             //         $articleModel->increment('ordered_quantity', $article["shipment_quantity"] * $customer['cotton_count']);
    //             //     }
    //             // }

    //             $invoice = new Invoice();
    //             $invoice->customer_id = $customer["id"];
    //             $invoice->invoice_no = $currentYear . '-' . $nextNumber;
    //             $invoice->shipment_no = $request->shipment_no;
    //             $invoice->netAmount = $shipment->netAmount * $customer['cotton_count'];
    //             $invoice->cotton_count = $customer['cotton_count'];
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

    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // SHIPMENT-BASED INVOICE
        if ($request->has('shipment_no')) {
            $validator = Validator::make($request->all(), [
                "shipment_no" => "required|string",
                "date" => "required|date",
                "customers_array" => "required|json",
                "printAfterSave" => "integer|in:0,1",
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $customers_array = json_decode($request->customers_array, true);
            $branches = app(ModuleBranchService::class);
            $shipmentQuery = $branches->applyRelatedScope(Shipment::with('articles.article'), 'shipments', 'invoices');
            $shipment = $this->applyDocumentNumberLookup($shipmentQuery, 'shipment_no', $request->shipment_no)->first();
            if (!$shipment) {
                throw ValidationException::withMessages([
                    'shipment_no' => 'Shipment not found for the selected branch.',
                ]);
            }

            $last_Invoice = Invoice::orderBy('id', 'desc')->first();

            if (!$last_Invoice) {
                $last_Invoice = new Invoice();
                $last_Invoice->invoice_no = '00-0000';
            }

            $currentYear = date("y");
            $lastNumberPart = substr($last_Invoice->invoice_no, -4);
            $nextNumber = str_pad((int)$lastNumberPart + 1, 4, '0', STR_PAD_LEFT);

            $invoiceNumbers = [];
            $totalCottonCount = (int) collect($customers_array)->sum(fn ($customer) => (int) ($customer['cotton_count'] ?? 0));
            $shipmentQuantities = collect($shipment->articles)
                ->groupBy('article_id')
                ->map(fn ($lines) => (int) $lines->sum('shipment_pcs') * $totalCottonCount);
            $this->validateInvoiceStock($shipmentQuantities);

            foreach ($customers_array as $customer) {
                $invoiceNo = $branches->shouldFilterRecords('invoices')
                    ? app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV')
                    : $currentYear . '-' . $nextNumber;
                $invoice = new Invoice();
                $invoice->customer_id = $customer["id"];
                $invoice->invoice_no = $invoiceNo;
                $invoice->shipment_no = $shipment->shipment_no;
                $invoice->netAmount = $shipment->netAmount * $customer['cotton_count'];
                $invoice->cotton_count = $customer['cotton_count'];
                $invoice->date = date("Y-m-d");
                $invoice->branch_id = $shipment->branch_id ?: $branches->branchIdForCreate('invoices');
                $invoice->save();

                // Store articles in invoice_articles table
                foreach ($shipment->articles as $shipmentArticle) {
                    InvoiceArticles::create([
                        'invoice_id' => $invoice->id,
                        'article_id' => $shipmentArticle->article_id,
                        'description' => $shipmentArticle->description,
                        'invoice_pcs' => $shipmentArticle->shipment_pcs * $customer['cotton_count'],
                    ]);
                }

                $invoiceNumbers[] = $invoiceNo;
                $this->notifyCustomerAboutInvoice((int) $customer['id'], $invoice->invoice_no);
                $nextNumber = str_pad((int)$nextNumber + 1, 4, '0', STR_PAD_LEFT);
            }

            if ($request->printAfterSave) {
                return redirect()->route('invoices.print')->with('invoiceNumbers', $invoiceNumbers);
            } else {
                return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
            }
        }
        // ORDER-BASED INVOICE
        else if ($request->has('order_no')) {
            $validator = Validator::make($request->all(), [
                "invoice_no" => "required|string",
                "order_no" => "required|string",
                "date" => "required|date",
                "netAmount" => "required|string",
                "articles_in_invoice" => "required|string",
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
            }

            $data = [
                'invoice_no' => app(ModuleBranchService::class)->shouldFilterRecords('invoices')
                    ? app(BranchSerialService::class)->next('invoices', Invoice::class, 'invoice_no', 'INV')
                    : $request->invoice_no,
                'order_no' => $request->order_no,
                'date' => $request->date,
                'netAmount' => $request->netAmount,
                'articles_in_invoice' => json_decode($request->articles_in_invoice, true),
            ];

            $orderQuery = app(ModuleBranchService::class)
                ->applyRelatedScope(Order::query(), 'orders', 'invoices');
            $orderDb = $this->applyDocumentNumberLookup($orderQuery, 'order_no', $data["order_no"])->first();
            if (!$orderDb) {
                throw ValidationException::withMessages([
                    'order_no' => 'Order not found for the selected branch.',
                ]);
            }
            $data['order_no'] = $orderDb->order_no;
            $orderArticleIds = collect($data['articles_in_invoice'])->pluck('order_article_id')->filter()->map(fn ($id) => (int) $id);
            $orderArticles = OrderArticles::whereIn('id', $orderArticleIds)->get()->keyBy('id');
            $invoiceQuantities = collect($data['articles_in_invoice'])
                ->groupBy(fn ($line) => (int) ($line['id'] ?? 0))
                ->map(fn ($lines) => (int) $lines->sum(fn ($line) => (int) ($line['invoice_quantity'] ?? 0)));

            foreach ($data['articles_in_invoice'] as $article) {
                $orderArticle = $orderArticles->get((int) ($article['order_article_id'] ?? 0));
                $invoiceQuantity = (int) ($article['invoice_quantity'] ?? 0);
                $pendingQuantity = max(0, (int) ($orderArticle?->ordered_pcs ?? 0) - (int) ($orderArticle?->dispatched_pcs ?? 0));

                if (!$orderArticle || $invoiceQuantity <= 0 || $invoiceQuantity > $pendingQuantity) {
                    throw ValidationException::withMessages([
                        'articles_in_invoice' => 'Invoice quantity cannot exceed pending order quantity.',
                    ]);
                }
            }

            $this->validateInvoiceStock($invoiceQuantities, $orderDb?->id);

            foreach ($data['articles_in_invoice'] as $article) {
                $orderArticleDb = OrderArticles::find($article['order_article_id']);
                $orderArticleDb->dispatched_pcs = (int) ($orderArticleDb->dispatched_pcs ?? 0) + (int) ($article['invoice_quantity'] ?? 0);
                $orderArticleDb->save();
            }

            $orderDb->load('articles');
            $orderedPcs = (int) $orderDb->articles->sum('ordered_pcs');
            $dispatchedPcs = (int) $orderDb->articles->sum('dispatched_pcs');
            if ($dispatchedPcs <= 0) {
                $orderDb->status = 'pending';
            } elseif ($dispatchedPcs < $orderedPcs) {
                $orderDb->status = 'partially_invoiced';
            } else {
                $orderDb->status = 'invoiced';
            }
            $orderDb->save();

            $data["netAmount"] = (int) str_replace(',', '', $data["netAmount"]);
            $data["customer_id"] = $orderDb["customer_id"];
            $data["branch_id"] = $orderDb->branch_id ?: app(ModuleBranchService::class)->branchIdForCreate('invoices');

            $invoice = Invoice::create($data);

            // Store articles in invoice_articles table
            foreach ($data['articles_in_invoice'] as $article) {
                InvoiceArticles::create([
                    'invoice_id' => $invoice->id,
                    'article_id' => $article['id'],
                    'description' => $article['description'] ?? null,
                    'invoice_pcs' => $article['invoice_quantity'],
                ]);
            }

            $this->notifyCustomerAboutInvoice((int) $data['customer_id'], $invoice->invoice_no);
        }

        return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
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
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $invoice->load(['customer.city', 'invoiceArticles.article', 'order', 'shipment']);

        $customers = Customer::with('city')
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

        $articles = Article::orderBy('article_no')
            ->get()
            ->mapWithKeys(fn (Article $article) => [
                $article->id => [
                    'text' => trim($article->article_no . ' | ' . ($article->description ?? '')),
                    'data_option' => [
                        'id' => $article->id,
                        'article_no' => $article->article_no,
                        'description' => $article->description,
                        'pcs_per_packet' => $article->pcs_per_packet,
                        'sales_rate' => $article->sales_rate,
                    ],
                ],
            ])
            ->all();

        $branches = app(ModuleBranchService::class);
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

        $shipmentsOptions = $branches->applyRelatedScope(Shipment::query(), 'shipments', 'invoices')
            ->orderByDesc('id')
            ->pluck('shipment_no', 'shipment_no')
            ->map(fn ($shipmentNo) => ['text' => $shipmentNo])
            ->toArray();

        $branchBranding = app(ModuleBranchService::class)->documentBranding('invoices');

        return view('invoices.edit', compact('invoice', 'customers', 'articles', 'branchBranding', 'ordersOptions', 'shipmentsOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, invoice $invoice)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');

        if ($resp = $this->denyIfNoRole(['developer'])) {
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

        $validated = $request->validate([
            'invoice_no' => ['required', 'string', 'max:255'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'shipment_no' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'netAmount' => ['required'],
            'cotton_count' => ['nullable', 'integer', 'min:0'],
            'articles' => ['required', 'array', 'min:1'],
            'articles.*.id' => ['nullable', 'integer', 'exists:invoice_articles,id'],
            'articles.*.article_id' => ['required', 'integer', 'exists:articles,id'],
            'articles.*.description' => ['nullable', 'string', 'max:255'],
            'articles.*.invoice_pcs' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $invoice->update([
                'invoice_no' => $validated['invoice_no'],
                'order_no' => $validated['order_no'] ?: null,
                'shipment_no' => $validated['shipment_no'] ?: null,
                'customer_id' => $validated['customer_id'],
                'date' => $validated['date'],
                'netAmount' => (int) str_replace(',', '', (string) $validated['netAmount']),
                'cotton_count' => $validated['cotton_count'] ?? null,
            ]);

            $keptIds = [];
            foreach ($validated['articles'] as $line) {
                $lineData = [
                    'article_id' => (int) $line['article_id'],
                    'description' => $line['description'] ?? null,
                    'invoice_pcs' => (int) $line['invoice_pcs'],
                ];

                if (!empty($line['id'])) {
                    $invoiceArticle = $invoice->invoiceArticles()->whereKey($line['id'])->firstOrFail();
                    $invoiceArticle->update($lineData);
                    $keptIds[] = (int) $invoiceArticle->id;
                    continue;
                }

                $created = $invoice->invoiceArticles()->create($lineData);
                $keptIds[] = (int) $created->id;
            }

            $invoice->invoiceArticles()
                ->when(!empty($keptIds), fn ($query) => $query->whereNotIn('id', $keptIds))
                ->delete();
        });

        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(invoice $invoice)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($invoice, 'invoices');

        if ($resp = $this->denyIfNoRole(['developer'])) {
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
            $invoice->invoiceArticles()->delete();
            $invoice->delete();
        });

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function print()
    {
        $invoiceNumbers = session('invoiceNumbers');

        if (!$invoiceNumbers) {
            return redirect()->route('invoices.create')->with('error', 'No invoices to print.');
        }

        $invoices = Invoice::with(["customer.city", 'invoiceArticles.article', 'shipment', 'order', 'branch'])->whereIn('invoice_no', $invoiceNumbers)->get();

        $invoicePayloads = $invoices->map(fn (Invoice $invoice) => $invoice->toFormattedArray()['data'])->values();

        return view("invoices.print", compact("invoices", "invoicePayloads"));
    }

    private function validateInvoiceStock($invoiceQuantities, ?int $excludeOrderId = null): void
    {
        $invoiceQuantities = collect($invoiceQuantities)
            ->filter(fn ($quantity, $articleId) => (int) $articleId > 0 && (int) $quantity > 0)
            ->map(fn ($quantity) => (int) $quantity);

        if ($invoiceQuantities->isEmpty()) {
            throw ValidationException::withMessages([
                'articles_in_invoice' => 'Please select at least one invoice article.',
            ]);
        }

        $branches = app(ModuleBranchService::class);
        $stockMap = $this->articleStockMap(
            $invoiceQuantities->keys(),
            $excludeOrderId,
            $branches->shouldFilterRecords('physical_quantities') ? $branches->selectedBranchIdForModule('invoices') : null
        );
        $articlesById = Article::query()
            ->whereIn('id', $invoiceQuantities->keys())
            ->get(['id', 'article_no'])
            ->keyBy('id');

        foreach ($invoiceQuantities as $articleId => $invoicePcs) {
            $availablePcs = (int) ($stockMap->get((int) $articleId)['current_stock_pcs'] ?? 0);

            if ((int) $invoicePcs > $availablePcs) {
                $articleNo = $articlesById->get((int) $articleId)?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles_in_invoice' => "Stock is less than invoice quantity for article: {$articleNo}. Available: {$availablePcs} pcs.",
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
