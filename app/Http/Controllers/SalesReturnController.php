<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InvoiceArticles;
use App\Models\PhysicalQuantity;
use App\Models\SalesReturn;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $sales_returns = SalesReturn::with('article', 'invoice.customer.city')->orderBy('id', 'desc')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        $branches = app(ModuleBranchService::class);

        if ($request->ajax()) {
            $sales_returns = $branches->applyScope(SalesReturn::orderByDesc('id'), 'sales_returns')
                ->applyFilters($request);

            return response()->json(['data' => $sales_returns, 'authLayout' => $authLayout]);
        }

        return view('sales-return.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $branches = app(ModuleBranchService::class);
        $customers = $branches->applyRelatedScope(Customer::whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->with('city'), 'customers', 'sales_returns')->get()->makeHidden('creator');

        $customerOptions = $customers->mapWithKeys(function ($customer) {
            return [$customer->id => ['text' => $customer->customer_name . ' | ' . $customer->city->short_title]];
        })->toArray();

        return view('sales-return.return', compact('customerOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'date' => 'required|date',
            'returns_data' => 'required|json',
            'type' => 'required|in:return,adjustment',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $returnLines = collect(json_decode($data['returns_data'], true))
            ->filter(fn ($line) => is_array($line))
            ->values();

        if ($returnLines->isEmpty()) {
            return redirect()->back()->with('error', 'Please select at least one article to return.')->withInput();
        }

        $totalAmount = 0;

        DB::transaction(function () use ($data, $returnLines, &$totalAmount) {
            $branches = app(ModuleBranchService::class);
            $branchId = $branches->branchIdForCreate('sales_returns');
            $createdReturnIds = [];
            $physicalQuantityLinksSalesReturn = Schema::hasColumn('physical_quantities', 'sales_return_id');

            foreach ($returnLines as $line) {
                $invoiceId = (int) ($line['invoice_id'] ?? 0);
                $articleId = (int) ($line['article_id'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($invoiceId <= 0 || $articleId <= 0 || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        'returns_data' => 'Invalid sales return line.',
                    ]);
                }

                $invoiceArticle = InvoiceArticles::with(['article', 'invoice.order', 'invoice.shipment'])
                    ->where('invoice_id', $invoiceId)
                    ->where('article_id', $articleId)
                    ->whereHas('invoice', function ($query) use ($data) {
                        $query->where('customer_id', $data['customer_id']);
                    })
                    ->first();

                if (!$invoiceArticle) {
                    throw ValidationException::withMessages([
                        'returns_data' => 'Selected invoice article was not found.',
                    ]);
                }

                $alreadyReturned = SalesReturn::where('invoice_id', $invoiceId)
                    ->where('article_id', $articleId)
                    ->sum('quantity');
                $remainingQuantity = max(0, (int) ($invoiceArticle->invoice_pcs ?? 0) - (int) $alreadyReturned);

                if ($quantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'returns_data' => "Return quantity cannot exceed remaining invoice quantity for {$invoiceArticle->article?->article_no}.",
                    ]);
                }

                $discount = optional($invoiceArticle->invoice?->order)->discount
                    ?? optional($invoiceArticle->invoice?->shipment)->discount
                    ?? 0;
                $salesRate = (float) ($invoiceArticle->article?->sales_rate ?? 0);
                $amount = (int) round($quantity * $salesRate * (1 - ((float) $discount / 100)));
                $pcsPerPacket = (float) ($invoiceArticle->article?->pcs_per_packet ?? 0);

                if ($pcsPerPacket <= 0) {
                    throw ValidationException::withMessages([
                        'returns_data' => "Master unit is missing for {$invoiceArticle->article?->article_no}.",
                    ]);
                }

                $salesReturn = SalesReturn::create([
                    'article_id' => $articleId,
                    'invoice_id' => $invoiceId,
                    'type' => $data['type'],
                    'date' => $data['date'],
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'branch_id' => $branchId,
                ]);
                $createdReturnIds[] = $salesReturn->id;

                $physicalQuantityData = [
                    'date' => $data['date'],
                    'article_id' => $articleId,
                    'packets' => $quantity / $pcsPerPacket,
                    'category' => $data['type'] === 'adjustment' ? 'adjustment' : 'sales_return',
                ];

                if ($physicalQuantityLinksSalesReturn) {
                    $physicalQuantityData['sales_return_id'] = $salesReturn->id;
                }
                if (Schema::hasColumn('physical_quantities', 'branch_id')) {
                    $physicalQuantityData['branch_id'] = app(ModuleBranchService::class)->branchIdForCreate('physical_quantities');
                }

                PhysicalQuantity::create($physicalQuantityData);

                if ($data['type'] === 'adjustment' && $invoiceArticle->invoice?->order) {
                    $this->reduceOrderDispatch(
                        $invoiceArticle->invoice->order,
                        $articleId,
                        $quantity
                    );
                }

                $totalAmount += $amount;
            }

            if ($totalAmount <= 0) {
                throw ValidationException::withMessages([
                    'returns_data' => 'Sales return amount must be greater than zero.',
                ]);
            }

            CustomerPayment::create([
                'customer_id' => $data['customer_id'],
                'date' => $data['date'],
                'type' => 'sales_return',
                'method' => 'return',
                'amount' => $totalAmount,
                'reff_no' => ($data['type'] === 'adjustment' ? 'ADJ-' : 'SR-') . ($createdReturnIds[0] ?? now()->format('YmdHis')),
                'remarks' => $data['type'] === 'adjustment' ? 'Sales adjustment' : 'Sales return',
                'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('customer_payments'),
            ]);
        });

        return redirect()->back()->with('success', 'Sales return saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesReturn $salesReturn)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($salesReturn, 'sales_returns');

        DB::transaction(function () use ($salesReturn) {
            PhysicalQuantity::where('sales_return_id', $salesReturn->id)->delete();
            $salesReturn->delete();
        });

        return redirect()->route('sales-returns.index')->with('success', 'Sales return deleted successfully.');
    }

    public function getDetails(Request $request)
    {
        if ($request->customer_id && $request->getReturnLines) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return response()->json([]);
            }

            return $this->returnableSalesReturnLines($customer);
        }

        if ($request->customer_id && $request->getArticles) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return response()->json([]);
            }

            return $customer->invoices()
                ->with('invoiceArticles.article')
                ->get()
                ->flatMap(fn($invoice) => $invoice->invoiceArticles)
                ->filter(fn($invoiceArticle) => (int) ($invoiceArticle->invoice_pcs ?? 0) > 0 && $invoiceArticle->article)
                ->groupBy('article_id')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'id' => $first->article_id,
                        'article_no' => $first->article?->article_no ?? '-',
                    ];
                })
                ->sortBy('article_no')
                ->values();
        }

        if ($request->customer_id && $request->article_id && $request->getInvoices) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return response()->json([]);
            }

            $invoices = $customer->invoices()
                ->with(['order', 'shipment', 'invoiceArticles'])
                ->get();

            $articleId = (int) $request->article_id;
            $article = Article::find($articleId);

            if (!$article) {
                return response()->json([]);
            }

            $salesRate = $article->sales_rate;

            return $invoices
                ->filter(function ($invoice) use ($articleId) {
                    return $invoice->invoiceArticles
                        ->pluck('article_id')
                        ->contains($articleId);
                })
                ->map(function ($invoice) use ($articleId, $salesRate) {
                    $articles_in_invoice = $invoice->invoiceArticles
                        ->filter(fn($article) => (int) $article->article_id === $articleId)
                        ->values();

                    $articles = $articles_in_invoice->map(fn($article_in_invoice) => [
                        'id' => (int) $article_in_invoice->article_id,
                        'invoice_quantity' => (int) ($article_in_invoice->invoice_pcs ?? 0),
                        'sales_rate' => $salesRate,
                    ])->all();

                    return [
                        'id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'date' => $invoice->date,
                        'articles_in_invoice' => $articles,
                        'discount' => optional($invoice->order)->discount
                                    ?? optional($invoice->shipment)->discount,
                        'sales_rate' => $salesRate,
                    ];
                })
                ->values();
        }

        return response()->json([]);
    }

    private function returnableSalesReturnLines(Customer $customer)
    {
        return $customer->invoices()
            ->with(['order', 'shipment', 'invoiceArticles.article', 'salesReturns'])
            ->orderByDesc('date')
            ->get()
            ->flatMap(function ($invoice) {
                $discount = optional($invoice->order)->discount
                    ?? optional($invoice->shipment)->discount
                    ?? 0;

                return $invoice->invoiceArticles
                    ->filter(fn ($invoiceArticle) => $invoiceArticle->article)
                    ->map(function ($invoiceArticle) use ($invoice, $discount) {
                        $alreadyReturned = $invoice->salesReturns
                            ->where('article_id', $invoiceArticle->article_id)
                            ->sum('quantity');
                        $invoiceQuantity = (int) ($invoiceArticle->invoice_pcs ?? 0);
                        $remainingQuantity = max(0, $invoiceQuantity - (int) $alreadyReturned);

                        if ($remainingQuantity <= 0) {
                            return null;
                        }

                        $salesRate = (float) ($invoiceArticle->article?->sales_rate ?? 0);

                        return [
                            'key' => $invoice->id . '-' . $invoiceArticle->article_id,
                            'invoice_id' => (int) $invoice->id,
                            'invoice_no' => $invoice->invoice_no,
                            'invoice_date' => optional($invoice->date)->toDateString(),
                            'article_id' => (int) $invoiceArticle->article_id,
                            'article_no' => $invoiceArticle->article?->article_no ?? '-',
                            'description' => $invoiceArticle->description ?? '',
                            'pcs_per_packet' => (int) ($invoiceArticle->article?->pcs_per_packet ?? 0),
                            'invoice_quantity' => $invoiceQuantity,
                            'already_returned' => (int) $alreadyReturned,
                            'remaining_quantity' => $remainingQuantity,
                            'sales_rate' => $salesRate,
                            'discount' => (float) $discount,
                        ];
                    })
                    ->filter();
            })
            ->values();
    }

    private function reduceOrderDispatch($order, int $articleId, int $quantity): void
    {
        $remaining = $quantity;
        $lines = $order->articles()
            ->where('article_id', $articleId)
            ->where('dispatched_pcs', '>', 0)
            ->orderByDesc('id')
            ->get();

        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $reduction = min($remaining, (int) $line->dispatched_pcs);
            $line->decrement('dispatched_pcs', $reduction);
            $remaining -= $reduction;
        }

        $order->load('articles');
        $orderedPcs = (int) $order->articles->sum('ordered_pcs');
        $dispatchedPcs = (int) $order->articles->sum('dispatched_pcs');
        $order->status = $dispatchedPcs <= 0
            ? 'pending'
            : ($dispatchedPcs < $orderedPcs ? 'partially_invoiced' : 'invoiced');
        $order->save();
    }
}
