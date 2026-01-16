<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Setup;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'guest'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        if ($request->ajax()) {
            $shipments = Expense::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $shipments, 'authLayout' => 'table']);
        }

        // $expenses = Expense::with('supplier', 'expenseSetups')->get();

        $expenseOptions = Setup::where('type', 'supplier_category')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => ['text' => $item->title]
            ])
            ->toArray();

        return view('expenses.index', compact('expenseOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $lastExpense = Expense::with('supplier', 'expenseSetups')->latest('id')->first();

        $suppliers = Supplier::whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->get();

        $suppliers_options = [];
        foreach ($suppliers as $supplier) {
            $suppliers_options[$supplier->id] = ["text" => $supplier->supplier_name, "data_option" => $supplier];
        }

        foreach ($suppliers as $supplier) {
            $categoriesIdArray = json_decode($supplier->categories_array, true);

            $categories = Setup::whereIn('id', $categoriesIdArray)
                ->where('type', 'supplier_category')
                ->get();

            $supplier["categories"] = $categories;

            $supplier["balance"] = 0.00;
        }

        return view('expenses.add', compact('suppliers_options', 'lastExpense'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
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
                    $exists = Expense::where('supplier_id', $request->supplier_id)
                        ->whereDate('date', $request->date)
                        ->where('amount', $request->amount)
                        ->where('expense', $request->expense)
                        ->where('reff_no', $value)
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

        $expense = Expense::create($request->all());

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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        return view('expenses.edit', compact('expense'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
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

        $data = $request->all();

        // Update the customer
        $expense->update($data);

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
