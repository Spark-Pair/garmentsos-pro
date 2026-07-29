<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\PhysicalQuantity;
use App\Services\PhysicalQuantityReportService;
use App\Services\ArticleStockService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PhysicalQuantityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PhysicalQuantityReportService $physicalQuantityReportService)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $grouped = $physicalQuantityReportService->getIndexRows($request, $request->limit ? (int) $request->limit : null);

            return response()->json([
                'data' => $grouped,
                'authLayout' => $authLayout
            ]);
        }

        // $allQuantities = PhysicalQuantity::with('article')->get();
        // $allShipments = Shipment::with('invoices.customer')->get();

        // // 🔹 Group by article_id (not id)
        // $grouped = $allQuantities->groupBy('article_id')->map(function ($group) {
        //     $first = $group->first();
        //     $article = $first->article;

        //     // Category-wise packets
        //     $categoryA = $group->where('category', 'a')->sum('packets');
        //     $categoryB = $group->where('category', 'b')->sum('packets');
        //     $categoryC = $group->where('category', 'c')->sum('packets');
        //     $total = $categoryA + $categoryB + $categoryC;

        //     $latestDate = $group->max('date');

        //     return (object)[
        //         'article_id' => $article->id,
        //         'article' => $article,
        //         'a_category' => $categoryA,
        //         'b_category' => $categoryB,
        //         'c_category' => $categoryC,
        //         'total_packets' => $total,
        //         'current_stock' => $total - ($article->sold_quantity / $article->pcs_per_packet),
        //         'latest_date' => $latestDate,
        //         'date' => date('d-M-y, D', strtotime($latestDate)),
        //     ];
        // })->values();

        // // 🔹 Attach shipment info
        // foreach ($allShipments as $shipment) {
        //     $shipment['articles'] = $shipment->getArticles();

        //     foreach ($shipment['articles'] as $article) {
        //         foreach ($grouped as $group) {
        //             if ($article['article']['id'] == $group->article_id) {
        //                 $cityTitle = strtolower($shipment->city);

        //                 if (!isset($group->city)) {
        //                     $group->city = [];
        //                 }

        //                 if (!in_array($cityTitle, $group->city)) {
        //                     $group->city[] = $cityTitle;
        //                 }
        //             }
        //         }
        //     }
        // }

        // // 🔹 Determine shipment type per article
        // foreach ($grouped as $group) {
        //     $cities = $group->city ?? [];

        //     $hasKarachi = in_array('karachi', $cities);
        //     $hasOther = count(array_filter($cities, fn($c) => $c !== 'karachi' && $c !== 'all')) > 0;
        //     $hasAll = in_array('all', $cities);

        //     if ($hasAll || ($hasKarachi && $hasOther)) {
        //         $group->shipment = 'All';
        //     } elseif ($hasKarachi) {
        //         $group->shipment = 'Karachi';
        //     } elseif ($hasOther) {
        //         $group->shipment = 'Other';
        //     } else {
        //         $group->shipment = null;
        //     }
        // }

        return view('physical-quantities.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        if (!$request->ajax()) {
            $articles = collect();

            return view('physical-quantities.create', compact('articles'));
        }

        $branches = app(ModuleBranchService::class);
        $articles = $branches->applyRelatedScope(Article::query(), 'articles', 'physical_quantities')
            // ->whereHas('production.work', function ($q) {
            //     $q->where('title', 'CMT');
            // })
            ->orderByDesc('id')
            ->get();

        $stockMap = app(ArticleStockService::class)->summaries(
            $articles->pluck('id'),
            null,
            $branches->shouldFilterRecords('physical_quantities') ? $branches->selectedBranchIdForModule('physical_quantities') : null
        );

        $articles = $articles->filter(function ($article) use ($stockMap) {
            $stock = $stockMap->get($article->id, []);
            $article['physical_packets'] = (float) ($stock['received_quantity_packets'] ?? 0);
            $article['physical_quantity'] = (int) ($stock['received_quantity_pcs'] ?? 0);

            $article['category'] = ucfirst(str_replace('_', ' ', $article['category']));
            $article['season']   = ucfirst(str_replace('_', ' ', $article['season']));
            $article['size']     = ucfirst(str_replace('_', '-', $article['size']));

            $article['total_quantity'] = (int) ($stock['total_quantity_pcs'] ?? 0);
            $article['total_packets'] = (float) ($stock['total_quantity_packets'] ?? 0);
            $article['remaining_quantity'] = (int) ($stock['remaining_quantity_pcs'] ?? 0);
            $article['remaining_packets'] = (float) ($stock['remaining_quantity_packets'] ?? 0);

            return $article['remaining_quantity'] > 0;
        })
            ->map(fn ($article) => [
                'id' => $article->id,
                'article_no' => $article->article_no,
                'date' => $article->date?->format('d-M-Y, D'),
                'category' => $article->category,
                'season' => $article->season,
                'size' => $article->size,
                'quantity' => $article->quantity,
                'extra_pcs' => $article->extra_pcs,
                'pcs_per_packet' => $article->pcs_per_packet,
                'processed_by' => $article->processed_by,
                'sales_rate' => $article->sales_rate,
                'image' => $article->image,
                'physical_packets' => $article->physical_packets,
                'physical_quantity' => $article->physical_quantity,
                'total_quantity' => $article->total_quantity,
                'total_packets' => $article->total_packets,
                'remaining_quantity' => $article->remaining_quantity,
                'remaining_packets' => $article->remaining_packets,
            ])
            ->values();
        return response()->json([
            'status' => 'success',
            'articles' => $articles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'article_id' => 'required|integer|exists:articles,id',
            'processed_by' => 'required|string',
            'pcs_per_packet' => 'required|integer|min:1',
            'packets' => 'required|integer|min:1',
            'category' => 'required|string',
        ]);

        if ($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['branch_id'] = app(ModuleBranchService::class)->branchIdForCreate('physical_quantities');

        Article::where('id', $data['article_id'])->update([
            'pcs_per_packet' => $data['pcs_per_packet'],
            'processed_by' => $data['processed_by'],
        ]);

        PhysicalQuantity::create($data);

        return redirect()->route('physical-quantities.create')->with('success', 'Physical quantity added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PhysicalQuantity $physicalQuantity)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($physicalQuantity, 'physical_quantities');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $physicalQuantity->load('article');

        return view('physical-quantities.edit', compact('physicalQuantity'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PhysicalQuantity $physicalQuantity)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($physicalQuantity, 'physical_quantities');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'processed_by' => 'required|string',
            'pcs_per_packet' => 'required|integer|min:1',
            'packets' => 'required|integer|min:1',
            'category' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        Article::where('id', $physicalQuantity->article_id)->update([
            'pcs_per_packet' => $data['pcs_per_packet'],
            'processed_by' => $data['processed_by'],
        ]);

        $physicalQuantity->update([
            'date' => $data['date'],
            'packets' => $data['packets'],
            'category' => $data['category'],
        ]);

        return redirect()->route('physical-quantities.index')->with('success', 'Physical quantity updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PhysicalQuantity $physicalQuantity)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($physicalQuantity, 'physical_quantities');

        $physicalQuantity->delete();

        return redirect()->route('physical-quantities.index')->with('success', 'Physical quantity deleted successfully.');
    }
}
