<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\PhysicalQuantity;
use App\Models\Shipment;
use App\Models\ShipmentArticles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PhysicalQuantityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        if ($request->ajax()) {
            $rows = PhysicalQuantity::with('article')
                ->orderByDesc('id')
                ->applyFilters($request, false) // 👈 important
                ->get();

            $grouped = $rows
                ->groupBy('article_id')
                ->map(function ($items) {

                    $model = $items->first();
                    $packets = $items->sum('packets');

                    // 🔹 Shipment cities
                    $cities = ShipmentArticles::where('article_id', $model->article_id)
                        ->whereHas('shipment')
                        ->with('shipment:id,city')
                        ->get()
                        ->pluck('shipment.city')
                        ->filter()
                        ->unique()
                        ->values();

                    $shipment = null;
                    if ($cities->isNotEmpty()) {
                        $hasKarachi = $cities->contains(fn($city) => strtolower($city) === 'karachi');

                        if ($hasKarachi && $cities->count() === 1) {
                            $shipment = 'karachi';
                        } elseif ($hasKarachi && $cities->count() > 1) {
                            $shipment = 'all';
                        } else {
                            $shipment = 'other';
                        }
                    }

                    return [
                        'article_id'        => $model->article_id,
                        'article_no'        => $model->article->article_no,
                        'processed_by'      => $model->article->processed_by,
                        'unit'              => $model->article->pcs_per_packet,
                        'total_quantity'    => floor($packets * $model->article->pcs_per_packet / 12) . ' - Dz. | ' . $packets . ' - Pkts.',
                        'received_quantity' => $packets . ' - Pkts.',
                        'current_stock'     => ($packets - ($model->article->sold_quantity / $model->article->pcs_per_packet)) . ' - Pkts.',
                        'a_category'        => $items->where('category', 'a')->sum('packets') . ' - Pkts.',
                        'b_category'        => $items->where('category', 'b')->sum('packets') . ' - Pkts.',
                        'c_category'        => ($model->article->quantity / $model->article->pcs_per_packet - $packets) . ' - Pkts.',
                        'remaining_quantity'=> $items->where('category', 'c')->sum('packets') . ' - Pkts.',
                        'shipment'          => ucfirst($shipment) ?? '-',
                        'onclick'           => "generateModal(this)",
                        'oncontextmenu'     => "generateContextMenu(event)",
                    ];
                })
                ->values();

            return response()->json([
                'data' => $grouped,
                'authLayout' => 'table'
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

        return view('physical-quantities.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $articles = Article::withSum('physicalQuantity', 'packets')
            // ->whereHas('production.work', function ($q) {
            //     $q->where('title', 'CMT');
            // })
            ->orderByDesc('id')
            ->get();

        foreach ($articles as $article) {
            $physical_quantity = $article['physical_quantity_sum_packets'];

            $article['physical_quantity'] = $physical_quantity
                ? $physical_quantity * $article->pcs_per_packet
                : 0;

            $article['category'] = ucfirst(str_replace('_', ' ', $article['category']));
            $article['season']  = ucfirst(str_replace('_', ' ', $article['season']));
            $article['size']    = ucfirst(str_replace('_', '-', $article['size']));
        }

        $articles = $articles->filter(function ($article) {
            return $article['physical_quantity'] < $article->quantity;
        });

        return view('physical-quantities.create', compact('articles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'article_id' => 'required|integer|exists:articles,id',
            'processed_by' => 'required|string',
            'pcs_per_packet' => 'required|integer|min:1',
            'packets' => 'required|integer|min:1',
        ]);

        if ($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        $article = Article::where('id', $data['article_id'])->update([
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PhysicalQuantity $physicalQuantity)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PhysicalQuantity $physicalQuantity)
    {
        //
    }
}
