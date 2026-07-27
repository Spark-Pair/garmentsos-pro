<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Fabric;
use App\Models\IssuedFabric;
use App\Models\Production;
use App\Models\ProductionTag;
use App\Models\ReturnFabric;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\ModuleBranchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FabricController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            // Added fabric entries
            $branches = app(ModuleBranchService::class);
            $addedFabrics = $branches->applyScope(Fabric::orderByDesc('id'), 'fabrics')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            // Issued fabric entries
            $issuedFabrics = $branches->applyScope(IssuedFabric::orderByDesc('id'), 'fabrics')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            // Return fabric entries
            $ReturnFabrics = $branches->applyScope(ReturnFabric::orderByDesc('id'), 'fabrics')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            $productionTags = collect();
            if (Schema::hasTable('production_tags')) {
                $productionTags = $branches->applyRelatedScope(
                        ProductionTag::with(['production.article', 'production.worker'])->orderByDesc('id'),
                        'productions',
                        'fabrics'
                    )
                    ->get()
                    ->map(function (ProductionTag $tag) {
                        return [
                            'id' => 'production-tag-' . $tag->id,
                            'type' => 'Used in Production',
                            'tag' => $tag->tag,
                            'quantity' => $tag->quantity,
                            'date' => optional($tag->production?->issue_date ?? $tag->created_at)->format('d-M-Y, D'),
                            'supplier_name' => $tag->production?->worker?->employee_name,
                            'employee_name' => $tag->production?->worker?->employee_name,
                            'fabric' => $tag->production?->article?->article_no,
                            'color' => '-',
                            'unit' => $tag->unit ?? '-',
                            'remarks' => 'Ticket: ' . ($tag->production?->ticket ?? '-'),
                            'created_at' => $tag->created_at,
                        ];
                    });
            }

            // Combine arrays manually
            $finalData = collect()
                ->merge($issuedFabrics)
                ->merge($ReturnFabrics)
                ->merge($productionTags)
                ->merge($addedFabrics)
                ->sortByDesc(function ($item) {
                    return Carbon::parse($item['created_at'])->timestamp;
                })
                ->values();

            return response()->json(['data' => $finalData, 'authLayout' => $authLayout]);
        }

        $fabrics_options = [];

        $fabrics = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'fabric'), 'setups', 'fabrics')->get();
        foreach ($fabrics as $fabric) {
            $fabrics_options[$fabric->id] = ["text" => $fabric->title,];
        }

        // return $fabrics_options;

        return view('fabrics.index', compact('fabrics_options', 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $lastRecord = $branches->applyScope(Fabric::latest()->with('supplier', 'fabric'), 'fabrics')->first();

        $fabricCategory = $branches->applyRelatedScope(Setup::where('title', 'Fabric'), 'setups', 'fabrics')->first();

        $suppliers = $branches->applyRelatedScope(Supplier::whereHas('user', function ($query) {
            $query->where('status', 'active');
        }), 'suppliers', 'fabrics')->get();

        if ($fabricCategory) {
            $suppliers = $suppliers->filter(function ($supplier) use ($fabricCategory) {
                $ids = json_decode($supplier->categories_array, true);
                return is_array($ids) && in_array($fabricCategory->id, $ids);
            });
        }

        $supplierPayloads = $this->supplierOptionPayloads($suppliers, 'fabrics');
        $suppliers_options = [];
        foreach ($suppliers as $supplier) {
            $supplierPayload = $supplierPayloads[(int) $supplier->id];
            $supplierPayload['categories_array'] = $supplier->categories_array;
            $suppliers_options[$supplier->id] = [
                "text" => $supplier->supplier_name,
                "data_option" => $supplierPayload,
            ];
        }

        $fabrics_options = [];

        $fabrics = $branches->applyRelatedScope(Setup::where('type', 'fabric'), 'setups', 'fabrics')->get();
        foreach ($fabrics as $fabric) {
            $fabrics_options[$fabric->id] = ["text" => $fabric->title, "data_option" => $fabric];
        }

        return view('fabrics.add', compact('lastRecord', 'suppliers_options', 'fabrics_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->validate([
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'fabric_id' => 'required|exists:setups,id',
            'color' => 'required|string',
            'unit' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'reff_no' => 'nullable|string',
            'remarks' => 'nullable|string|max:255',
            'tag' => 'required|string|max:255',
        ]);

        $branches = app(ModuleBranchService::class);
        $supplier = $branches->applyRelatedScope(Supplier::query(), 'suppliers', 'fabrics')->find($request->supplier_id);
        $fabricSetup = $branches->applyRelatedScope(Setup::where('type', 'fabric'), 'setups', 'fabrics')->find($request->fabric_id);
        if (!$supplier || !$fabricSetup) {
            return redirect()->back()->withErrors(['supplier_id' => 'Selected supplier/fabric is not available for this branch.'])->withInput();
        }

        Fabric::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'date' => $request->date,
            'supplier_id' => $request->supplier_id,
            'fabric_id' => $request->fabric_id,
            'color' => $request->color,
            'unit' => $request->unit,
            'quantity' => $request->quantity,
            'reff_no' => $request->reff_no,
            'remarks' => $request->remarks,
            'tag' => $request->tag,
        ], 'fabrics'));

        return redirect()->route('fabrics.create')->with('success', 'Fabric added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fabric $fabric)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($fabric, 'fabrics');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fabric $fabric)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($fabric, 'fabrics');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fabric $fabric)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($fabric, 'fabrics');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fabric $fabric)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($fabric, 'fabrics');
    }

    public function issue()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $tags_options = [];

        $branches = app(ModuleBranchService::class);
        $all_fabrics = $branches->applyScope(Fabric::query(), 'fabrics')->get()
            ->groupBy('tag')
            ->map(function ($items) {
                return [
                    'tag' => $items->first()->tag,
                    'unit' => $items->first()->unit,
                    'quantity' => $items->sum('quantity'),
                ];
            })
            ->values();

        foreach($all_fabrics as $fabric) {
            $total_issued = $branches->applyScope(IssuedFabric::where('tag', $fabric['tag']), 'fabrics')->sum('quantity') ?? 0;
            $fabric['avalaible_sock'] = $fabric['quantity'] - $total_issued;
            if ($fabric['avalaible_sock'] > 0) {
                $tags_options[$fabric['tag']] = ['text' => $fabric['tag'], "data_option" => json_encode($fabric)];
            }
        }

        $workers_options = [];

        $all_workers = $branches->applyRelatedScope(Employee::whereHas('type', function ($query) {
                $query->whereIn('title', ['Cutting', 'Cut to Pack']);
            }), 'employees', 'fabrics')
            ->get();

        foreach ($all_workers as $worker) {
            $workers_options[$worker->id] = ['text' => $worker->employee_name];
        }

        return view('fabrics.issue', compact('tags_options', 'workers_options'));
    }

    public function issuePost(Request $request) {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->validate([
            'date' => 'required|date',
            'tag' => 'required|string|max:255',
            'worker_id' => 'required|exists:employees,id',
            'quantity' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        $worker = app(ModuleBranchService::class)->applyRelatedScope(Employee::query(), 'employees', 'fabrics')->find($request->worker_id);
        if (!$worker) {
            return redirect()->back()->withErrors(['worker_id' => 'Selected worker is not available for this branch.'])->withInput();
        }

        IssuedFabric::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'date' => $request->date,
            'tag' => $request->tag,
            'worker_id' => $request->worker_id,
            'quantity' => $request->quantity,
            'remarks' => $request->remarks,
        ], 'fabrics'));

        return redirect()->route('fabrics.issue')->with('success', 'Fabric added successfully.');
    }

    public function return(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $tags_options = [];
        $worker_id = $request->worker_id;
        $date = $request->date;

        if ($worker_id && $date) {
            // 1️⃣ Get all fabrics issued to the worker until the given date
            $branches = app(ModuleBranchService::class);
            $all_fabrics = $branches->applyScope(IssuedFabric::where('worker_id', $worker_id), 'fabrics')
                ->whereDate('date', '<=', $date)
                ->get()
                ->groupBy('tag')
                ->map(function ($items) {
                    return [
                        'tag' => $items->first()->tag,
                        'quantity' => $items->sum('quantity'),
                    ];
                })
                ->values()
                ->toArray();

            // 2️⃣ Get cutting work id
            $cutting_id = $branches->applyRelatedScope(Setup::where('type', 'worker_type'), 'setups', 'fabrics')
                ->where('title', 'Cutting')
                ->value('id');

            // 3️⃣ Get all production tags for the worker & cutting work
            $allTags = $branches->applyRelatedScope(Production::where('worker_id', $worker_id), 'productions', 'fabrics')
                ->where('work_id', $cutting_id)
                ->where(function ($q) use ($date) {
                    $q->whereDate('issue_date', '<=', $date)
                      ->orWhereDate('receive_date', '<=', $date);
                })
                ->pluck('tags');

            $mergedTags = [];
            foreach ($allTags as $tags) {
                $decoded = is_string($tags) ? json_decode($tags, true) : $tags;
                if (is_array($decoded)) {
                    $mergedTags = array_merge($mergedTags, $decoded);
                }
            }

            // 4️⃣ Sum production quantity by tag
            $productionQuantities = [];
            foreach ($mergedTags as $item) {
                $tag = $item['tag'];
                $qty = $item['quantity'] ?? 0;

                if (!isset($productionQuantities[$tag])) {
                    $productionQuantities[$tag] = 0;
                }

                $productionQuantities[$tag] += $qty;
            }

            // 5️⃣ Get returned fabrics for the worker until date
            $returnedFabrics = $branches->applyScope(ReturnFabric::where('worker_id', $worker_id), 'fabrics')
                ->whereDate('date', '<=', $date)
                ->get()
                ->groupBy('tag')
                ->map(function ($items) {
                    return [
                        'tag' => $items->first()->tag,
                        'quantity' => $items->sum('quantity'),
                    ];
                })
                ->values()
                ->toArray();

            // 6️⃣ Sum returned quantity by tag
            $returnQuantities = [];
            foreach ($returnedFabrics as $fabric) {
                $tag = $fabric['tag'];
                $qty = $fabric['quantity'] ?? 0;

                if (!isset($returnQuantities[$tag])) {
                    $returnQuantities[$tag] = 0;
                }

                $returnQuantities[$tag] += $qty;
            }

            // 7️⃣ Prepare tag options with remaining quantity
            $tags_options = [];

            foreach ($all_fabrics as $fabric) {
                $tag = $fabric['tag'];
                $issuedQty = $fabric['quantity'];

                $prodQty = $productionQuantities[$tag] ?? 0;
                $returnQty = $returnQuantities[$tag] ?? 0;

                $remaining = $issuedQty - $prodQty - $returnQty;

                $fabric['remaining'] = $remaining;
                $fabric['issued_quantity'] = $issuedQty;
                $fabric['produced_quantity'] = $prodQty;
                $fabric['returned_quantity'] = $returnQty;

                if ($remaining > 0) {
                    $tags_options[$tag] = [
                        'text' => $tag,
                        'data_option' => json_encode($fabric),
                    ];
                }
            }

            // Fallback: if no tags found, allow issued minus returned (ignore production)
            if (empty($tags_options) && !empty($all_fabrics)) {
                foreach ($all_fabrics as $fabric) {
                    $tag = $fabric['tag'];
                    $issuedQty = $fabric['quantity'];
                    $returnQty = $returnQuantities[$tag] ?? 0;

                    $remaining = $issuedQty - $returnQty;

                    $fabric['remaining'] = $remaining;
                    $fabric['issued_quantity'] = $issuedQty;
                    $fabric['produced_quantity'] = $productionQuantities[$tag] ?? 0;
                    $fabric['returned_quantity'] = $returnQty;

                    if ($remaining > 0) {
                        $tags_options[$tag] = [
                            'text' => $tag,
                            'data_option' => json_encode($fabric),
                        ];
                    }
                }
            }
        }

        $workers_options = [];

        $all_workers = app(ModuleBranchService::class)->applyRelatedScope(Employee::whereHas('type', function ($query) {
                $query->whereIn('title', ['Cutting', 'Cut to Pack']);
            }), 'employees', 'fabrics')
            ->get();

        foreach ($all_workers as $worker) {
            $workers_options[$worker->id] = ['text' => $worker->employee_name];
        }

        return view('fabrics.return', compact('tags_options', 'workers_options'));
    }

    public function returnPost(Request $request) {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->validate([
            'worker_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'tag' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        $worker = app(ModuleBranchService::class)->applyRelatedScope(Employee::query(), 'employees', 'fabrics')->find($request->worker_id);
        if (!$worker) {
            return redirect()->back()->withErrors(['worker_id' => 'Selected worker is not available for this branch.'])->withInput();
        }

        ReturnFabric::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'worker_id' => $request->worker_id,
            'date' => $request->date,
            'tag' => $request->tag,
            'quantity' => $request->quantity,
            'remarks' => $request->remarks,
        ], 'fabrics'));

        return redirect()->route('fabrics.return')->with('success', 'Fabric added successfully.');
    }
}
