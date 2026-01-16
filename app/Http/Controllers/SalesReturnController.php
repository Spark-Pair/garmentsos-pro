<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $sales_returns = SalesReturn::with('article', 'invoice.customer.city')->orderBy('id', 'desc')->get();

        if ($request->ajax()) {
            $sales_returns = SalesReturn::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $sales_returns, 'authLayout' => 'table']);
        }

        return view('sales-return.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $customers = Customer::whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->with('city')->get()->makeHidden('creator');

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
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'article_id' => 'required|integer|exists:articles,id',
            'invoice_id' => 'required|integer|exists:invoices,id',
            'date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'amount' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        SalesReturn::create($data);

        Article::find($data['article_id'])->decrement('sold_quantity', $data['quantity']);

        CustomerPayment::create([
            'customer_id' => $data['customer_id'],
            'date' => $data['date'],
            'type' => 'sales_return',
            'method' => 'return',
            'amount' => $data['amount'],
        ]);

        return redirect()->back()->with('success', 'Sales return successfully.');
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
        //
    }

    public function getDetails(Request $request)
    {
        if ($request->customer_id && $request->getArticles) {
            return Article::where('sold_quantity', '>', 0)
                ->select(['id', 'article_no'])
                ->get();
        }

        if ($request->customer_id && $request->article_id && $request->getInvoices) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return collect();
            }

            // Load all invoices with relations in one go
            $invoices = $customer->invoices()
                ->with(['order', 'shipment'])
                ->get();

            // Collect all article IDs from invoices (only requested one)
            $articleId = (int) $request->article_id;

            // Load article once (not in every loop)
            $article = Article::find($articleId);

            if (!$article) {
                return collect();
            }

            $salesRate = $article->sales_rate;

            return $invoices
                ->filter(function ($invoice) use ($articleId) {
                    return collect($invoice->articles_in_invoice)
                        ->pluck('id')
                        ->contains($articleId);
                })
                ->map(function ($invoice) use ($articleId, $salesRate) {
                    // Keep only the requested article
                    $articles_in_invoice = collect($invoice->articles_in_invoice)
                        ->filter(fn($article) => (int) $article['id'] === $articleId)
                        ->values();

                    $articles = $articles_in_invoice->map(fn($article_in_invoice) => [
                        'id' => $article_in_invoice['id'],
                        'invoice_quantity' => $article_in_invoice['invoice_quantity'],
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
                });
        }

        return collect();
    }
}
