<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderArticles;
use App\Models\PaymentProgram;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Orders\OrderInvoiceSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'customer', 'store_keeper'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $ordersQuery = app(ModuleBranchService::class)
                ->applyScope(Order::with('customer.city', 'articles.article', 'branch')->orderByDesc('id'), 'orders');

            if ($this->isCustomerRole()) {
                $customer = $this->currentCustomer();
                if (!$customer) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Customer account not linked with this user.',
                    ], 403);
                }
                $ordersQuery->where('customer_id', $customer->id);
            }

            $orders = $ordersQuery->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        // $orders = Order::with('customer.city', 'articles.article')->get();

        // // Collect all article IDs from ordered articles
        // $articleIds = $orders->flatMap(function ($order) {
        //     return collect(json_decode($order->articles, true))->pluck('id');
        // })->unique();

        // // Fetch all required articles in a single query
        // $articles = Article::whereIn('id', $articleIds)->get()->keyBy('id');

        // $orders = $orders->transform(function ($order) use ($articles) {
        //     // Step 1: Decode and normalize articles to indexed array
        //     $orderedArticlesRaw = json_decode($order->articles, true) ?? [];
        //     $orderedArticlesArray = array_values($orderedArticlesRaw); // Normalize to indexed array

        //     // Step 2: Map through each ordered article
        //     $orderedArticles = collect($orderedArticlesArray)->map(function ($orderedArticle) use ($articles) {
        //         if (isset($articles[$orderedArticle['id']])) {
        //             $orderedArticle['article'] = $articles[$orderedArticle['id']];
        //         }

        //         $orderedArticle['ordered_pcs'] = max(0, $orderedArticle['ordered_pcs'] - ($orderedArticle['invoice_quantity'] ?? 0));

        //         return $orderedArticle;
        //     })->filter(function ($orderedArticle) {
        //         return $orderedArticle['ordered_pcs'] > 0;
        //     })->values(); // 👈 ensures final collection is indexed (not associative)

        //     // Step 3: Put it back into the order
        //     $order['articles'] = $orderedArticles;

        //     return $order;
        // })
        // ->filter(function ($order) {
        //     return $order['articles']->isNotEmpty();
        // })
        // ->values();

        // foreach ($orders as $key => $order) {
        //     $order['previous_balance'] = $order->customer->calculateBalance(null, $order->date, false, false);
        //     $order['current_balance'] = $order['previous_balance'] + $order['netAmount'];
        // }

        return view('orders.index', compact( 'authLayout'));
        // return $orders;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'customer'])) {
            return $resp;
        }

        $customers_options = [];
        $articles = [];

        if ($request->date) {
            $branches = app(ModuleBranchService::class);
            if ($this->isCustomerRole()) {
                $customer = $this->currentCustomer();
                if ($customer && $customer->date && $customer->date->toDateString() <= $request->date) {
                    $customer->load('city');
                    $customers = collect([$customer]);
                } else {
                    $customers = collect();
                }
            } else {
                $customers = $branches->applyRelatedScope(Customer::with('city'), 'customers', 'orders')
                    ->whereHas('user', function ($query) {
                        $query->where('status', 'active');
                    })
                    ->where('date', '<=', $request->date)
                    ->select('id', 'customer_name', 'person_name', 'urdu_title', 'phone_number', 'date', 'city_id', 'address')
                    ->get();
            }

            if ($request->filled('include_customer_id')) {
                $includedCustomer = Customer::with('city')
                    ->whereKey((int) $request->include_customer_id)
                    ->first();

                if ($includedCustomer && !$customers->contains('id', $includedCustomer->id)) {
                    $customers->push($includedCustomer);
                }
            }

            foreach ($customers as $customer) {
                $customers_options[(int) $customer->id] = $this->customerOption($customer);
            }

            $articles = $branches->applyRelatedScope(Article::where('date', '<=', $request->date), 'articles', 'orders')
                ->where('sales_rate', '>', 0)
                ->whereNotNull(['category', 'fabric_type'])
                ->orderByDesc('id')
                ->get();

            $physicalQuantityEnabled = $branches->isClientModuleEnabled('physical_quantities');
            $stockMap = $physicalQuantityEnabled ? $this->articleStockMap(
                $articles->pluck('id'),
                $request->filled('exclude_order_id') ? (int) $request->exclude_order_id : null,
                $branches->shouldFilterRecords('physical_quantities') ? $branches->selectedBranchIdForModule('orders') : null
            ) : collect();

            foreach ($articles as $article) {
                $stock = $stockMap->get($article->id, []);
                $article['current_stock'] = (int) ($stock['current_stock_pcs'] ?? 0);
                $article['current_stock_packets'] = (float) ($stock['current_stock_packets'] ?? 0);
                $article['orderable_quantity'] = $physicalQuantityEnabled ? (int) ($stock['orderable_quantity_pcs'] ?? 0) : 999999999;
                $article['orderable_quantity_packets'] = (float) ($stock['orderable_quantity_packets'] ?? 0);
                $article['total_quantity'] = (int) ($stock['total_quantity_pcs'] ?? 0);
                $article['ordered_quantity'] = (int) ($stock['ordered_quantity_pcs'] ?? 0);
                $article['b_c_quantity'] = (int) ($stock['b_category_pcs'] ?? 0) + (int) ($stock['c_category_pcs'] ?? 0);

                $article['category'] = ucfirst(str_replace('_', ' ', $article['category']));
                $article['season'] = ucfirst(str_replace('_', ' ', $article['season']));
                $article['size'] = ucfirst(str_replace('_', '-', $article['size']));
            }

            $articles = $articles
                ->filter(fn (Article $article) => !$physicalQuantityEnabled || (int) $article->orderable_quantity > 0)
                ->values();
        }

        $branches = app(ModuleBranchService::class);
        $last_order = Order::orderby('id', 'desc')->first();

        if (!$last_order) {
            $last_order = new Order();
            $last_order->order_no = '00-0000';
        }
        if ($branches->shouldFilterRecords('orders')) {
            $last_order = new Order();
            $last_order->order_no = app(BranchSerialService::class)->next('orders', Order::class, 'order_no');
        }
        $nextOrderNo = app(BranchSerialService::class)->next('orders', Order::class, 'order_no');
        $defaultOrderDiscount = $branches->getDefaultOrderDiscountForBranch($branches->selectedBranchIdForModule('orders'));

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'articles' => $articles,
                'customers_options' => array_values($customers_options),
                'default_order_discount_percent' => $defaultOrderDiscount,
                'next_order_no' => $nextOrderNo,
            ]);
        }

        $branchBranding = app(ModuleBranchService::class)->documentBranding('orders');
        return view('orders.generate', compact('last_order', 'branchBranding', 'defaultOrderDiscount', 'nextOrderNo'));
        // return $articles;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'customer'])) {
            return $resp;
        }

        if ($this->isCustomerRole()) {
            $customer = $this->currentCustomer();
            if (!$customer) {
                return redirect()->back()->with('error', 'Customer account not linked with this user.');
            }
            $request->merge(['customer_id' => $customer->id]);
        }

        $rules = [
            'date' => 'required|date',
            'customer_id' => 'required|integer|exists:customers,id',
            'discount' => 'required|integer|min:0|max:100',
            'netAmount' => 'required|string',
            'articles' => 'required|json',
        ];

        if ($this->supportsOrderDeliverToColumn()) {
            $rules['deliver_to'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $createdOrder = DB::transaction(function () use ($request) {
            $articles = json_decode($request->articles, true) ?? [];
            $discount = max(0, min(100, (int) $request->discount));
            $this->validateOrderArticleStock($articles);

            $branches = app(ModuleBranchService::class);
            $branchId = $branches->branchIdForCreate('orders');
            $orderNo = app(BranchSerialService::class)->next('orders', Order::class, 'order_no');

            $orderData = [
                'date' => $request->date,
                'customer_id' => $request->customer_id,
                'discount' => $discount,
                'netAmount' => str_replace(',', '', $request->netAmount),
                'articles' => $request->articles,
                'order_no' => $orderNo,
                'branch_id' => $branchId,
            ];

            if ($this->supportsOrderDeliverToColumn()) {
                $orderData['deliver_to'] = $request->deliver_to;
            }

            $order = Order::create($orderData);

            foreach ($articles as $articleData) {
                OrderArticles::create([
                    'order_id' => $order['id'],
                    'article_id' => $articleData['id'],
                    'description' => $articleData['description'] ?? null,
                    'ordered_pcs' => $articleData['ordered_quantity'] ?? $articleData['ordered_pcs'] ?? 0,
                ]);
            }

            $customer = Customer::find($order['customer_id']);

            if ($customer && $customer['category'] == 'cash') {
                $nextProgramNo = ((int) PaymentProgram::query()->lockForUpdate()->max('program_no')) + 1;
                while (PaymentProgram::query()->where('program_no', $nextProgramNo)->exists()) {
                    $nextProgramNo++;
                }

                PaymentProgram::create([
                    'program_no' => $nextProgramNo,
                    'date' => $order['date'],
                    'order_no' => $order['order_no'],
                    'customer_id' => $order['customer_id'],
                    'category' => 'waiting',
                    'amount' => $order['netAmount'],
                    'branch_id' => $order->branch_id ?: $branches->branchIdForCreate('payment_programs'),
                ]);
            }

            return $order;
        });

        if ($this->isCustomerRole() && $createdOrder) {
            try {
                $customer = $this->currentCustomer();
                $creatorName = Auth::user()?->name ?: ($customer?->customer_name ?? 'Customer');
                $notificationPayload = [
                    'title' => 'Customer Order Created',
                    'message' => "{$creatorName} ne order {$createdOrder->order_no} create kiya hai.",
                    'type' => 'info',
                    'url' => route('orders.index', ['open_order' => $createdOrder->id]),
                    'persist' => true,
                    'target_roles' => ['admin', 'store_keeper'],
                ];
                $storedNotificationPayload = [
                    't' => 'Customer Order Created',
                    'm' => "{$creatorName} ne order {$createdOrder->order_no} create kiya hai.",
                    'tp' => 'info',
                    'u' => route('orders.index', ['open_order' => $createdOrder->id]),
                    'p' => true,
                    'tr' => ['admin', 'store_keeper'],
                ];

                $receivers = \App\Models\User::query()
                    ->whereIn('role', ['admin', 'store_keeper'])
                    ->where('status', 'active')
                    ->pluck('id');

                foreach ($receivers as $receiverId) {
                    Notification::create([
                        'senderId' => Auth::id(),
                        'recieverId' => $receiverId,
                        'caption' => json_encode($storedNotificationPayload),
                    ]);
                }

                event(new NewNotificationEvent($notificationPayload));
            } catch (\Throwable $e) {
                Log::error('Customer order notification failed', [
                    'order_id' => $createdOrder->id,
                    'order_no' => $createdOrder->order_no,
                    'auth_user_id' => Auth::id(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // if ($request->generateInvoiceAfterSave) {
        //     return redirect()->route('invoices.create')->with('orderNumber', $order->order_no);
        // } else {
            if ($request->boolean('printAfterSave')) {
                return redirect()->route('orders.index', [
                    'open_order' => $createdOrder->id,
                    'print_order' => 1,
                ])->with('success', 'Order generated successfully. Order No. : ' . $createdOrder->order_no);
            }

            return redirect()->route('orders.create')->with('success', 'Order generated successfully. Order No. : ' . $createdOrder->order_no);
        // }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($order, 'orders');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        if (
            !$this->checkRole(['developer', 'owner', 'admin', 'accountant']) &&
            !app_can('orders', 'override')
        ) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($order, 'orders');

        $order->load([
            'customer.city',
            'articles.article',
            'creator',
        ]);

        $orderPayload = [
            'order_no' => $order->order_no,
            'id' => $order->id,
            'branch_id' => $order->branch_id,
            'branch_branding' => app(ModuleBranchService::class)->documentBranding('orders', $order),
            'date' => $order->date?->format('Y-m-d'),
            'created_at' => $order->created_at?->format('Y-m-d, h:i A'),
            'creator' => $order->creator ? [
                'id' => $order->creator->id,
                'name' => $order->creator->name,
            ] : null,
            'netAmount' => $order->netAmount,
            'deliver_to' => $order->deliver_to,
            'customer' => [
                'id' => $order->customer?->id,
                'customer_name' => $order->customer?->customer_name,
                'urdu_title' => $order->customer?->urdu_title,
                'address' => $order->customer?->address,
                'phone_number' => $order->customer?->phone_number,
                'city' => [
                    'title' => $order->customer?->city?->title,
                ],
            ],
            'articles' => $order->articles->map(function ($orderArticle) {
                return [
                    'article_id' => $orderArticle->article_id,
                    'ordered_pcs' => $orderArticle->ordered_pcs,
                    'dispatched_pcs' => $orderArticle->dispatched_pcs,
                    'description' => $orderArticle->description,
                    'article' => [
                        'id' => $orderArticle->article?->id,
                        'article_no' => $orderArticle->article?->article_no,
                        'sales_rate' => $orderArticle->article?->sales_rate,
                        'pcs_per_packet' => $orderArticle->article?->pcs_per_packet,
                        'image' => $orderArticle->article?->image,
                        'category' => $orderArticle->article?->category,
                        'fabric_type' => $orderArticle->article?->fabric_type,
                        'season' => $orderArticle->article?->season,
                        'size' => $orderArticle->article?->size,
                    ],
                ];
            })->toArray(),
        ];

        $customers_options = [];
        if ($order->customer) {
            $customers_options[(int) $order->customer->id] = $this->customerOption($order->customer);
        }

        if (Auth::user()?->role === 'developer' || app_can('orders', 'override')) {
            $branches = app(ModuleBranchService::class);
            $customers = $branches->applyRelatedScope(Customer::with('city'), 'customers', 'orders')
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })
                ->where('date', '<=', $order->date?->format('Y-m-d') ?? now()->toDateString())
                ->select('id', 'customer_name', 'person_name', 'urdu_title', 'phone_number', 'date', 'city_id', 'address')
                ->get();

            foreach ($customers->sortBy('customer_name') as $customer) {
                $customers_options[(int) $customer->id] = $this->customerOption($customer);
            }
        }

        $branchBranding = app(ModuleBranchService::class)->documentBranding('orders', $order);

        return view('orders.edit', compact('order', 'orderPayload', 'branchBranding', 'customers_options'));
    }

    public function update(Request $request, Order $order)
    {
        if (
            !$this->checkRole(['developer', 'owner', 'admin', 'accountant']) &&
            !app_can('orders', 'override')
        ) {
            return redirect(route('home'))
                ->with('error', 'You do not have permission to access this page.');
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($order, 'orders');

        $isDeveloper = Auth::user()?->role === 'developer' || app_can('orders', 'override');
        $rules = [
            'discount'   => 'required|integer|min:0|max:100',
            'netAmount'  => 'required|string',
            'articles'   => 'required',
            'date' => $isDeveloper ? 'required|date' : 'nullable',
            'customer_id' => $isDeveloper ? 'required|integer|exists:customers,id' : 'nullable',
        ];

        if ($this->supportsOrderDeliverToColumn()) {
            $rules['deliver_to'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request, $order, $isDeveloper) {

            $netAmount = (int) str_replace(',', '', $request->netAmount);
            $articles = is_string($request->articles) ? json_decode($request->articles, true) : $request->articles;
            $articles = is_array($articles) ? $articles : [];
            $sync = app(OrderInvoiceSyncService::class);
            $existingDispatched = $sync->invoicedPcsByArticle($order);

            $this->validateOrderArticleStock($articles, $order->id, $existingDispatched);

            // Update order
            $orderUpdates = [
                'netAmount' => $netAmount,
                'discount'  => $request->discount,
            ];

            if ($this->supportsOrderDeliverToColumn()) {
                $orderUpdates['deliver_to'] = $request->deliver_to;
            }

            if ($isDeveloper) {
                $orderUpdates['date'] = $request->date;
                $orderUpdates['customer_id'] = $request->customer_id;
            }

            $order->update($orderUpdates);

            // Reset order articles
            $order->articles()->delete();

            $usedDispatched = collect();

            foreach ($articles as $article) {
                $articleId = (int) ($article['id'] ?? 0);
                $dispatchedPcs = $usedDispatched->has($articleId)
                    ? 0
                    : (int) ($existingDispatched->get($articleId) ?? 0);

                OrderArticles::create([
                    'order_id'    => $order->id,
                    'article_id'  => $articleId,
                    'description' => $article['description'] ?? null,
                    'ordered_pcs' => $article['ordered_pcs'] ?? 0,
                    'dispatched_pcs' => $dispatchedPcs,
                ]);

                $usedDispatched->put($articleId, true);
            }

            $sync->recalculateOrderDispatch($order);

            $paymentProgramUpdates = ['amount' => $netAmount];
            if ($isDeveloper) {
                $paymentProgramUpdates['date'] = $order->date;
                $paymentProgramUpdates['customer_id'] = $order->customer_id;
            }
            $order->paymentPrograms()->update($paymentProgramUpdates);
        });

        return redirect()->route('orders.index')->with('success', 'Order updated successfully. Order No: ' . $order->order_no);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        if (Auth::user()?->role !== 'developer' && !app_can('orders', 'override')) {
            return redirect(route('orders.index'))->with('error', 'Only Developer can delete orders.');
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($order, 'orders');

        if ($order->invoices()->exists()) {
            return redirect()->route('orders.index')
                ->with('error', 'This order has invoices. Delete or edit the invoices first, then delete the order.');
        }

        DB::transaction(function () use ($order) {
            $order->articles()->delete();
            $order->paymentPrograms()->delete();
            $order->delete();
        });

        return redirect()->route('orders.index')->with('success', 'Order deleted successfully.');
    }

    private function validateOrderArticleStock(array $articles, ?int $excludeOrderId = null, $existingDispatched = null): void
    {
        $lines = collect($articles)
            ->filter(fn ($line) => is_array($line))
            ->map(function ($line) {
                return [
                    'article_id' => (int) ($line['id'] ?? 0),
                    'ordered_pcs' => (int) ($line['ordered_quantity'] ?? $line['ordered_pcs'] ?? 0),
                ];
            })
            ->filter(fn ($line) => $line['article_id'] > 0 && $line['ordered_pcs'] > 0)
            ->groupBy('article_id')
            ->map(fn ($group) => (int) $group->sum('ordered_pcs'));

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'articles' => 'Please select at least one article.',
            ]);
        }

        $existingDispatched = collect($existingDispatched ?? [])->mapWithKeys(fn ($pcs, $articleId) => [(int) $articleId => (int) $pcs]);
        foreach ($existingDispatched as $articleId => $dispatchedPcs) {
            if ((int) $dispatchedPcs > 0 && (int) ($lines->get((int) $articleId, 0)) < (int) $dispatchedPcs) {
                $article = Article::find((int) $articleId);
                throw ValidationException::withMessages([
                    'articles' => 'Order quantity cannot be less than already invoiced quantity for article: ' . ($article?->article_no ?? $articleId),
                ]);
            }
        }

        $branches = app(ModuleBranchService::class);
        $physicalQuantityEnabled = $branches->isClientModuleEnabled('physical_quantities');
        $branchId = $branches->shouldFilterRecords('physical_quantities')
            ? $branches->selectedBranchIdForModule('orders')
            : null;
        $stockMap = $this->articleStockMap($lines->keys(), $excludeOrderId, $branchId);
        $articlesById = Article::query()
            ->whereIn('id', $lines->keys())
            ->get(['id', 'article_no', 'pcs_per_packet'])
            ->keyBy('id');

        foreach ($lines as $articleId => $orderedPcs) {
            $article = $articlesById->get((int) $articleId);
            $pcsPerPacket = (int) ($article?->pcs_per_packet ?? 0);
            if ($pcsPerPacket > 0 && $orderedPcs % $pcsPerPacket !== 0) {
                throw ValidationException::withMessages([
                    'articles' => "Order quantity for {$article?->article_no} must make whole packets of {$pcsPerPacket} pcs.",
                ]);
            }

            $dispatchedPcs = (int) ($existingDispatched->get((int) $articleId) ?? 0);
            $currentOrderPcs = $excludeOrderId
                ? (int) OrderArticles::where('order_id', $excludeOrderId)->where('article_id', (int) $articleId)->sum('ordered_pcs')
                : 0;
            $maxOrderPcs = (int) ($stockMap->get((int) $articleId)['orderable_quantity_pcs'] ?? 0) + $currentOrderPcs;

            if ($physicalQuantityEnabled && $orderedPcs > $maxOrderPcs) {
                $articleNo = $article?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles' => "Order quantity exceeds the remaining article quantity for {$articleNo}. Available: {$maxOrderPcs} pcs.",
                ]);
            }
        }
    }

    private function customerOption(Customer $customer): array
    {
        $customer->loadMissing('city');

        return [
            'text' => trim($customer->customer_name . ' | ' . ($customer->city?->title ?? '-')),
            'data_option' => [
                'id' => $customer->id,
                'customer_name' => $customer->customer_name,
                'person_name' => $customer->person_name,
                'urdu_title' => $customer->urdu_title,
                'phone_number' => $customer->phone_number,
                'address' => $customer->address,
                'date' => $customer->date?->format('Y-m-d'),
                'city' => [
                    'id' => $customer->city?->id,
                    'title' => $customer->city?->title,
                    'short_title' => $customer->city?->short_title,
                ],
            ],
        ];
    }

    private function supportsOrderDeliverToColumn(): bool
    {
        static $supports = null;

        return $supports ??= Schema::hasColumn('orders', 'deliver_to');
    }

    public function customerArticleHistory(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'customer'])) {
            return $resp;
        }

        $customerId = (int) $request->customer_id;

        if ($this->isCustomerRole()) {
            $customer = $this->currentCustomer();
            if (!$customer || (int) $customer->id !== $customerId) {
                return response()->json(['status' => 'error', 'message' => 'Customer account not linked with this user.'], 403);
            }
        }

        if (!$customerId) {
            return response()->json(['status' => 'success', 'history' => []]);
        }

        $excludeOrderId = $request->filled('exclude_order_id') ? (int) $request->exclude_order_id : null;

        $ordersQuery = app(ModuleBranchService::class)
            ->applyScope(Order::query(), 'orders')
            ->where('customer_id', $customerId)
            ->with('articles:id,order_id,article_id');

        if ($excludeOrderId) {
            $ordersQuery->where('id', '!=', $excludeOrderId);
        }

        $orders = $ordersQuery->get(['id', 'order_no', 'customer_id']);

        $history = [];
        foreach ($orders as $order) {
            foreach ($order->articles as $orderArticle) {
                $articleId = (int) $orderArticle->article_id;
                $history[$articleId] ??= [];
                if (!in_array($order->order_no, $history[$articleId], true)) {
                    $history[$articleId][] = $order->order_no;
                }
            }
        }

        $history = collect($history)->map(fn ($orderNos) => [
            'count' => count($orderNos),
            'order_nos' => $orderNos,
        ]);

        return response()->json(['status' => 'success', 'history' => $history]);
    }
}
