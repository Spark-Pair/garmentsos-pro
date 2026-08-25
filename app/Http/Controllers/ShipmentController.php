<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentArticles;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
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
        $branches = app(ModuleBranchService::class);

        if ($request->ajax()) {
            $shipments = $branches->applyScope(Shipment::with('articles.article')->orderByDesc('id'), 'shipments')
                ->applyFilters($request);

            return response()->json(['data' => $shipments, 'authLayout' => $authLayout]);
        }

        // $shipments = Shipment::with('articles.article')->get();

        // if ($shipments->isNotEmpty()) {
        //     foreach ($shipments as $shipment) {
        //         // Invoice exist check
        //         $shipment->isInvoiceHas = Invoice::where('shipment_no', $shipment->shipment_no)->exists();
        //     }
        // }

        return view('shipments.index', compact( 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $customers_options = [];
        $articles = [];
        $branches = app(ModuleBranchService::class);

        if ($request->date) {
            $customers = $branches->applyRelatedScope(Customer::with('city'), 'customers', 'shipments')
                ->whereHas('user', fn($query) => $query->where('status', 'active'))
                ->where('date', '<=', $request->date)
                ->select('id', 'customer_name', 'person_name', 'urdu_title', 'phone_number', 'date', 'city_id', 'address')
                ->get();

            foreach ($customers as $customer) {
                $customers_options[(int)$customer->id] = [
                    'text' => $customer->customer_name . ' | ' . $customer->city->title,
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

            $articles = $branches->applyRelatedScope(Article::where('date', '<=', $request->date), 'articles', 'shipments')
                ->where('sales_rate', '>', 0)
                ->whereNotNull(['category', 'fabric_type'])
                ->orderByDesc('id')
                ->get();

            $stockMap = $this->articleStockMap(
                $articles->pluck('id'),
                null,
                $branches->shouldFilterRecords('physical_quantities') ? $branches->selectedBranchIdForModule('shipments') : null
            );

            foreach ($articles as $article) {
                $stock = $stockMap->get($article->id, []);
                $article['current_stock'] = (int) ($stock['current_stock_pcs'] ?? 0);
                $article['current_stock_packets'] = (float) ($stock['current_stock_packets'] ?? 0);
                $article['orderable_quantity'] = (int) ($stock['orderable_quantity_pcs'] ?? 0);
                $article['orderable_quantity_packets'] = (float) ($stock['orderable_quantity_packets'] ?? 0);
                $article['total_quantity'] = (int) ($stock['total_quantity_pcs'] ?? 0);
                $article['ordered_quantity'] = (int) ($stock['ordered_quantity_pcs'] ?? 0);
            }

            $articles = $articles
                ->filter(fn (Article $article) => (int) $article->orderable_quantity > 0)
                ->values();
        }

        $last_shipment = new Shipment();
        $last_shipment->shipment_no = app(BranchSerialService::class)->next('shipments', Shipment::class, 'shipment_no', null, 4);

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'articles' => $articles
            ]);
        }

        $branchBranding = $branches->documentBranding('shipments');

        return view('shipments.generate', compact('customers_options', 'last_shipment', 'branchBranding'));
        // return $articles;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // return $request->all();

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'discount' => 'required|integer',
            'netAmount' => 'required|string',
            'articles' => 'required|json',
            'city' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $createdShipment = DB::transaction(function () use ($request) {
            $branches = app(ModuleBranchService::class);
            $articles = json_decode($request->articles, true) ?? [];
            $this->validateShipmentArticleStock($articles);

            $shipment = Shipment::create([
                'date' => $request->date,
                'discount' => $request->discount,
                'netAmount' => str_replace(',', '', $request->netAmount),
                'articles' => $request->articles,
                'city' => $request->city,
                'shipment_no' => app(BranchSerialService::class)->next('shipments', Shipment::class, 'shipment_no', null, 4),
                'branch_id' => $branches->branchIdForCreate('shipments'),
            ]);

            foreach ($articles as $article) {
                ShipmentArticles::create([
                    'shipment_id' => $shipment['id'],
                    'article_id' => $article['id'],
                    'description' => $article['description'] ?? null,
                    'shipment_pcs' => $article['shipment_quantity'] ?? 0,
                ]);
            }

            return $shipment;
        });

        if ($request->boolean('printAfterSave')) {
            return redirect()->route('shipments.index', ['open_shipment' => $createdShipment->id, 'print_shipment' => 1])
                ->with('success', 'Shipment generated successfully. Shipment No. : ' . $createdShipment->shipment_no);
        }

        return redirect()->route('shipments.create')->with('success', 'Shipment generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipment $shipment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shipment $shipment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $shipment->load('articles.article');
        $stockMap = $this->articleStockMap($shipment->articles->pluck('article_id'));

        $shipmentPayload = [
            'date' => $shipment->date?->format('Y-m-d'),
            'articles' => $shipment->articles->map(function ($shipmentArticle) use ($stockMap) {
                $stock = $stockMap->get($shipmentArticle->article_id, []);

                return [
                    'shipment_pcs' => $shipmentArticle->shipment_pcs,
                    'description' => $shipmentArticle->description,
                    'article' => [
                        'id' => $shipmentArticle->article?->id,
                        'article_no' => $shipmentArticle->article?->article_no,
                        'sales_rate' => $shipmentArticle->article?->sales_rate,
                        'pcs_per_packet' => $shipmentArticle->article?->pcs_per_packet,
                        'quantity' => $shipmentArticle->article?->quantity,
                        'extra_pcs' => $shipmentArticle->article?->extra_pcs,
                        'fabric_type' => $shipmentArticle->article?->fabric_type,
                        'orderable_quantity' => (int) ($stock['orderable_quantity_pcs'] ?? 0),
                        'orderable_quantity_packets' => (float) ($stock['orderable_quantity_packets'] ?? 0),
                        'current_stock' => (int) ($stock['current_stock_pcs'] ?? 0),
                        'current_stock_packets' => (float) ($stock['current_stock_packets'] ?? 0),
                        'image' => $shipmentArticle->article?->image,
                        'category' => $shipmentArticle->article?->category,
                        'season' => $shipmentArticle->article?->season,
                        'size' => $shipmentArticle->article?->size,
                    ],
                ];
            })->toArray(),
        ];

        $branchBranding = app(ModuleBranchService::class)->documentBranding('cargos');

        return view('shipments.edit', compact('shipment', 'shipmentPayload', 'branchBranding'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipment $shipment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'netAmount' => 'required|string',
            'articles' => 'required|json',
            'city' => 'required|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request, $shipment) {
            $articles = json_decode($request->articles, true) ?? [];
            $existingShipmentPcs = $shipment->articles()
                ->get(['article_id', 'shipment_pcs'])
                ->groupBy('article_id')
                ->map(fn ($lines) => (int) $lines->sum('shipment_pcs'));

            $this->validateShipmentArticleStock($articles, $existingShipmentPcs);

            ShipmentArticles::where('shipment_id', $shipment->id)->delete();

            $shipment->update([
                'netAmount' => str_replace(',', '', $request->netAmount),
                'articles' => $request->articles,
                'city' => $request->city,
            ]);

            foreach ($articles as $article) {
                ShipmentArticles::create([
                    'shipment_id' => $shipment['id'],
                    'article_id' => $article['id'],
                    'description' => $article['description'] ?? null,
                    'shipment_pcs' => $article['shipment_quantity'] ?? 0,
                ]);
            }
        });

        return redirect()->route('shipments.index')->with('success', 'Shipment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }

    private function validateShipmentArticleStock(array $articles, $existingShipmentPcs = null): void
    {
        $lines = collect($articles)
            ->filter(fn ($line) => is_array($line))
            ->map(function ($line) {
                return [
                    'article_id' => (int) ($line['id'] ?? 0),
                    'shipment_pcs' => (int) ($line['shipment_quantity'] ?? 0),
                ];
            })
            ->filter(fn ($line) => $line['article_id'] > 0 && $line['shipment_pcs'] > 0)
            ->groupBy('article_id')
            ->map(fn ($group) => (int) $group->sum('shipment_pcs'));

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'articles' => 'Please select at least one article.',
            ]);
        }

        $existingShipmentPcs = collect($existingShipmentPcs ?? []);
        $branches = app(ModuleBranchService::class);
        $branchId = $branches->shouldFilterRecords('physical_quantities')
            ? $branches->selectedBranchIdForModule('shipments')
            : null;
        $stockMap = $this->articleStockMap($lines->keys(), null, $branchId);
        $articlesById = Article::query()
            ->whereIn('id', $lines->keys())
            ->get(['id', 'article_no', 'pcs_per_packet'])
            ->keyBy('id');

        foreach ($lines as $articleId => $shipmentPcs) {
            $article = $articlesById->get((int) $articleId);
            $pcsPerPacket = (int) ($article?->pcs_per_packet ?? 0);
            if ($pcsPerPacket > 0 && $shipmentPcs % $pcsPerPacket !== 0) {
                throw ValidationException::withMessages([
                    'articles' => "Shipment quantity for {$article?->article_no} must make whole packets of {$pcsPerPacket} pcs.",
                ]);
            }

            $availablePcs = (int) ($stockMap->get((int) $articleId)['orderable_quantity_pcs'] ?? 0);
            $maxShipmentPcs = max(
                $availablePcs,
                (int) ($existingShipmentPcs->get((int) $articleId) ?? 0)
            );

            if ($shipmentPcs > $maxShipmentPcs) {
                $articleNo = $article?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles' => "Shipment quantity exceeds the remaining article quantity for {$articleNo}. Available: {$maxShipmentPcs} pcs.",
                ]);
            }
        }
    }
}
