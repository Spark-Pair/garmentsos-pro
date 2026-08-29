<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'guest', 'supplier'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        $branches = app(ModuleBranchService::class);

        if ($request->ajax()) {
            $expensesQuery = $branches->applyScope(Expense::with(['supplier', 'expenseSetups'])->orderByDesc('id'), 'expenses');

            if ($this->isSupplierRole()) {
                $supplier = $this->currentSupplier();
                if (!$supplier) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Supplier account not linked with this user.',
                    ], 403);
                }
                $expensesQuery->where('supplier_id', $supplier->id);
            }

            $shipments = $expensesQuery->applyFilters($request);

            $totalAmount = (float) $shipments->sum(fn ($item) => (float) str_replace(',', '', $item['amount'] ?? 0));

            return response()->json([
                'data' => $shipments,
                'authLayout' => $authLayout,
                'calculations' => [
                    'total_amount' => $totalAmount,
                ],
            ]);
        }

        // $expenses = Expense::with('supplier', 'expenseSetups')->get();

        $expenseOptions = Setup::where('type', 'supplier_category')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => ['text' => $item->title]
            ])
            ->toArray();

        return view('expenses.index', compact('expenseOptions', 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $adjustmentSetup = Setup::firstOrCreate([
            'type' => 'supplier_category',
            'title' => 'Adjustment',
        ]);

        $branches = app(ModuleBranchService::class);
        $lastExpense = $branches->applyScope(Expense::with('supplier', 'expenseSetups')->latest('id'), 'expenses')->first();

        $suppliers = $branches->applyRelatedScope(Supplier::whereHas('user', function ($query) {
            $query->where('status', 'active');
        }), 'suppliers', 'expenses')->get();

        $supplierPayloads = $this->supplierOptionPayloads($suppliers, 'expenses');
        $suppliers_options = [];
        foreach ($suppliers as $supplier) {
            $categoriesIdArray = json_decode($supplier->categories_array, true);

            $categories = Setup::whereIn('id', $categoriesIdArray)
                ->where('type', 'supplier_category')
                ->get();

            $supplierPayload = $supplierPayloads[(int) $supplier->id];
            $supplierPayload['categories'] = $categories;
            $supplierPayload['categories_array'] = $supplier->categories_array;
            $suppliers_options[$supplier->id] = [
                "text" => $supplier->supplier_name,
                "data_option" => $supplierPayload,
            ];
        }

        return view('expenses.add', compact('suppliers_options', 'lastExpense', 'adjustmentSetup'));
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
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'expense' => 'required|exists:setups,id',
            'amount' => 'required|integer|min:0',
            'lot_no' => 'nullable|integer',
            'remarks' => 'nullable|string|max:255',

            // composite uniqueness
            'reff_no' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($request) {
                    $branches = app(ModuleBranchService::class);
                    $exists = Expense::where('supplier_id', $request->supplier_id)
                        ->whereDate('date', $request->date)
                        ->where('amount', $request->amount)
                        ->where('expense', $request->expense)
                        ->where('reff_no', $value)
                        ->when($branches->shouldFilterRecords('expenses'), function ($query) use ($branches) {
                            $query->where('branch_id', $branches->branchIdForCreate('expenses'));
                        })
                        ->exists();

                    if ($exists) {
                        $fail('This reference number already exists for the same supplier, date, expense and amount.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        $expense = Expense::create([
            'date' => $request->date,
            'supplier_id' => $request->supplier_id,
            'expense' => $request->expense,
            'reff_no' => $request->reff_no,
            'amount' => $request->amount,
            'lot_no' => $request->lot_no,
            'remarks' => $request->remarks,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('expenses'),
        ]);

        return redirect()->back()->with('success', 'Expense added successfully! ID: ' . $expense->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $adjustmentSetup = Setup::firstOrCreate([
            'type' => 'supplier_category',
            'title' => 'Adjustment',
        ]);

        $canFullyEdit = Auth::user()?->role === 'developer' || app_can('expenses', 'override');
        $supplier = $expense->supplier;
        $supplierDataPayload = [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'categories' => collect($supplier->categories)->map(fn($category) => [
                'id' => $category->id,
                'title' => $category->title,
            ])->values()->all(),
        ];

        $suppliers_options = [];
        if ($canFullyEdit) {
            $branches = app(ModuleBranchService::class);
            $suppliers = $branches->applyRelatedScope(Supplier::whereHas('user', function ($query) {
                $query->where('status', 'active');
            }), 'suppliers', 'expenses')->get();

            if (!$suppliers->contains('id', $supplier->id)) {
                $suppliers->push($supplier);
            }

            $supplierPayloads = $this->supplierOptionPayloads($suppliers, 'expenses');
            foreach ($suppliers as $supplierOption) {
                $categoriesIdArray = json_decode($supplierOption->categories_array, true) ?: [];
                $supplierPayload = $supplierPayloads[(int) $supplierOption->id];
                $supplierPayload['categories'] = Setup::whereIn('id', $categoriesIdArray)
                    ->where('type', 'supplier_category')
                    ->get(['id', 'title'])
                    ->toArray();
                $supplierPayload['categories_array'] = $supplierOption->categories_array;
                $suppliers_options[$supplierOption->id] = [
                    'text' => $supplierOption->supplier_name,
                    'data_option' => $supplierPayload,
                ];
            }
        }

        return view('expenses.edit', compact(
            'expense',
            'adjustmentSetup',
            'supplierDataPayload',
            'suppliers_options',
            'canFullyEdit'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $canFullyEdit = Auth::user()?->role === 'developer' || app_can('expenses', 'override');

        $validator = Validator::make($request->all(), [
            'date' => $canFullyEdit ? 'required|date' : 'nullable',
            'supplier_id' => $canFullyEdit ? 'required|integer|exists:suppliers,id' : 'nullable',
            'expense' => 'required|exists:setups,id',
            'reff_no' => 'required|integer',
            'amount' => 'required|integer|min:0',
            'lot_no' => 'nullable|integer',
            'remarks' => 'nullable|string|max:255'
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $updates = [
            'expense' => $request->expense,
            'reff_no' => $request->reff_no,
            'amount' => $request->amount,
            'lot_no' => $request->lot_no,
            'remarks' => $request->remarks,
        ];

        if ($canFullyEdit) {
            $updates['date'] = $request->date;
            $updates['supplier_id'] = $request->supplier_id;
        }

        $expense->update($updates);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        //
    }
}
