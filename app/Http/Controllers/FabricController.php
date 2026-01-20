<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Fabric;
use App\Models\IssuedFabric;
use App\Models\Production;
use App\Models\ReturnFabric;
use App\Models\Setup;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FabricController extends Controller
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
            // Added fabric entries
            $addedFabrics = Fabric::orderByDesc('id')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            // Issued fabric entries
            $issuedFabrics = IssuedFabric::orderByDesc('id')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            // Return fabric entries
            $ReturnFabrics = ReturnFabric::orderByDesc('id')
                ->applyFilters($request, false) // 👈 important
                ->get()->map->toFormattedArray();

            // Combine arrays manually
            $finalData = collect()
                ->merge($issuedFabrics)
                ->merge($ReturnFabrics)
                ->merge($addedFabrics)
                ->sortByDesc(function ($item) {
                    $date = Carbon::parse($item['date'])->format('Y-m-d');
                    $time = Carbon::parse($item['created_at'])->format('H:i:s');

                    return Carbon::createFromFormat('Y-m-d H:i:s', "$date $time");
                })
                ->values();

            return response()->json(['data' => $finalData, 'authLayout' => 'table']);
        }

        $fabrics_options = [];

        $fabrics = Setup::where('type', 'fabric')->get();
        foreach ($fabrics as $fabric) {
            $fabrics_options[$fabric->id] = ["text" => $fabric->title,];
        }

        // return $fabrics_options;

        return view('fabrics.index', compact('fabrics_options'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $lastRecord = Fabric::latest()->with('supplier', 'fabric')->first();

        $fabricCategory = Setup::where('title', 'Fabric')->first();

        $suppliers = Supplier::whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->get();

        if ($fabricCategory) {
            $suppliers = $suppliers->filter(function ($supplier) use ($fabricCategory) {
                $ids = json_decode($supplier->categories_array, true);
                return is_array($ids) && in_array($fabricCategory->id, $ids);
            });
        }

        $suppliers_options = [];
        foreach ($suppliers as $supplier) {
            $suppliers_options[$supplier->id] = ["text" => $supplier->supplier_name, "data_option" => $supplier];
        }

        $fabrics_options = [];

        $fabrics = Setup::where('type', 'fabric')->get();
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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
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

        Fabric::create($request->all());

        return redirect()->route('fabrics.create')->with('success', 'Fabric added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fabric $fabric)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fabric $fabric)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fabric $fabric)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fabric $fabric)
    {
        //
    }

    public function issue()
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $tags_options = [];

        $all_fabrics = Fabric::all()
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
            $total_issued = IssuedFabric::where('tag', $fabric['tag'])->sum('quantity') ?? 0;
            $fabric['avalaible_sock'] = $fabric['quantity'] - $total_issued;
            if ($fabric['avalaible_sock'] > 0) {
                $tags_options[$fabric['tag']] = ['text' => $fabric['tag'], "data_option" => json_encode($fabric)];
            }
        }

        $workers_options = [];

        $all_workers = Employee::whereHas('type', function ($query) {
                $query->whereIn('title', ['Cutting', 'Cut to Pack']);
            })
            ->get();

        foreach ($all_workers as $worker) {
            $workers_options[$worker->id] = ['text' => $worker->employee_name];
        }

        return view('fabrics.issue', compact('tags_options', 'workers_options'));
    }

    public function issuePost(Request $request) {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $request->validate([
            'date' => 'required|date',
            'tag' => 'required|string|max:255',
            'worker_id' => 'required|exists:employees,id',
            'quantity' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        IssuedFabric::create($request->all());

        return redirect()->route('fabrics.issue')->with('success', 'Fabric added successfully.');
    }

    public function return(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $tags_options = [];
        $worker_id = $request->worker_id;
        $date = $request->date;

        if ($worker_id && $date) {
            // 1️⃣ Get all fabrics issued to the worker until the given date
            $all_fabrics = IssuedFabric::where('worker_id', $worker_id)
                ->where('date', '<=', $date)
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
            $cutting_id = Setup::where('type', 'worker_type')
                ->where('title', 'Cutting')
                ->value('id');

            // 3️⃣ Get all production tags for the worker & cutting work
            $allTags = Production::where('worker_id', $worker_id)
                ->where('work_id', $cutting_id)
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
            $returnedFabrics = ReturnFabric::where('worker_id', $worker_id)
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
        }

        $workers_options = [];

        $all_workers = Employee::whereHas('type', function ($query) {
                $query->whereIn('title', ['Cutting', 'Cut to Pack']);
            })
            ->get();

        foreach ($all_workers as $worker) {
            $workers_options[$worker->id] = ['text' => $worker->employee_name];
        }

        return view('fabrics.return', compact('tags_options', 'workers_options'));
    }

    public function returnPost(Request $request) {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $request->validate([
            'worker_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'tag' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        ReturnFabric::create($request->all());

        return redirect()->route('fabrics.return')->with('success', 'Fabric added successfully.');
    }
}
