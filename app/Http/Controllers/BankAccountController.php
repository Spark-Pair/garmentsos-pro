<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        // $bankAccounts = BankAccount::with('subCategory', 'bank')->orderBy('id', 'desc')->get();

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $bankAccounts = app(ModuleBranchService::class)
                ->applyScope(BankAccount::orderByDesc('id'), 'bank_accounts')
                ->applyFilters($request);

            return response()->json(['data' => $bankAccounts, 'authLayout' => $authLayout]);
        }

        $bank_options = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'bank_accounts')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => ['text' => $item->title]
            ])
            ->toArray();

        return view("bank-accounts.index", compact('bank_options', "authLayout"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $bank_options = [];
        $banks = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'bank_accounts')->get();

        if ($banks->count() > 0) {
            foreach ($banks as $bank) {
                $bank_options[(int)$bank->id] = ['text' => $bank->title];
            }
        }
        return view("bank-accounts.create", compact('bank_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $categoryTypeMap = [
            'supplier' => 'App\Models\Supplier',
            'customer' => 'App\Models\Customer',
            'self'     => null,
        ];

        $categoryType = $categoryTypeMap[$request->category] ?? null;

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:self,supplier,customer',
            'sub_category' => 'nullable|integer',
            'bank_id' => 'required|string',
            'account_title' => [
                'required',
                'string',
                Rule::unique('bank_accounts', 'account_title')
                    ->where(function ($query) use ($request, $categoryType) {
                        return $query->where('sub_category_type', $categoryType)
                                    ->where('sub_category_id', $request->sub_category)
                                    ->where('bank_id', $request->bank_id);
                    }),
            ],
            'date' => 'required|date',
            'remarks' => 'nullable|string',
            'account_no' => 'nullable|string|unique:bank_accounts,account_no',
            'cheque_book_serial' => 'nullable|array',
            'cheque_book_serial.start' => 'nullable|numeric',
            'cheque_book_serial.end' => 'nullable|numeric|gte:cheque_book_serial.start',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $branches = app(ModuleBranchService::class);
        $bank = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'bank_accounts')->find($request->bank_id);
        if (!$bank) {
            return redirect()->back()->withErrors(['bank_id' => 'Selected bank is not available for this branch.'])->withInput();
        }

        $subCategoryModel = null;

        // Dynamically associate sub_category based on category
        if ($request->category === 'supplier') {
            $subCategoryModel = $branches->applyRelatedScope(Supplier::query(), 'suppliers', 'bank_accounts')->find($request->sub_category);
        } elseif ($request->category === 'customer') {
            $subCategoryModel = $branches->applyRelatedScope(Customer::query(), 'customers', 'bank_accounts')->find($request->sub_category);
        }

        // Ensure subCategoryModel is not null
        if ($request->category !== 'self' && !$subCategoryModel) {
            return redirect()->back()->withErrors(['sub_category' => 'Invalid sub category'])->withInput();
        }

        $chqbk_serial_start = $request->input('cheque_book_serial.start');
        $chqbk_serial_end = $request->input('cheque_book_serial.end');

        $bankAccount = new BankAccount($branches->assignBranchOnCreate([
            'category' => $request->category,
            'bank_id' => $request->bank_id,
            'account_title' => $request->account_title,
            'date' => $request->date,
            'account_no' => $request->account_no,
            'chqbk_serial_start' => $chqbk_serial_start,
            'chqbk_serial_end' => $chqbk_serial_end,
        ], 'bank_accounts'));

        if ($subCategoryModel) {
            $subCategoryModel->bankAccounts()->save($bankAccount);
        } else {
            $bankAccount->save(); // Self category ke liye direct save
        }

        Cache::forget('category_data:self_account');
        return redirect()->route('bank-accounts.create')->with('success', 'Bank account added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bankAccount, 'bank_accounts');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BankAccount $bankAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bankAccount, 'bank_accounts');

        $bank_options = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'bank_accounts')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => ['text' => $item->title]
            ])
            ->toArray();

        return view('bank-accounts.create', compact('bankAccount', 'bank_options'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bankAccount, 'bank_accounts');

        $validated = $request->validate([
            'category' => ['required', Rule::in(['self', 'customer', 'supplier'])],
            'sub_category' => ['nullable', 'integer'],
            'bank_id' => ['required', 'integer', 'exists:setups,id'],
            'account_title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'account_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'cheque_book_serial' => ['nullable', 'array'],
            'cheque_book_serial.start' => ['nullable', 'integer', 'min:1'],
            'cheque_book_serial.end' => ['nullable', 'integer', 'min:1', 'gte:cheque_book_serial.start'],
            'status' => ['nullable', Rule::in(['active', 'in_active'])],
        ]);

        $branches = app(ModuleBranchService::class);
        $bank = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'bank_accounts')->find($validated['bank_id']);
        if (!$bank) {
            return redirect()->back()->withErrors(['bank_id' => 'Selected bank is not available for this branch.'])->withInput();
        }

        $subCategoryModel = null;
        if ($validated['category'] === 'supplier') {
            $subCategoryModel = $branches->applyRelatedScope(Supplier::query(), 'suppliers', 'bank_accounts')->find($validated['sub_category'] ?? null);
        } elseif ($validated['category'] === 'customer') {
            $subCategoryModel = $branches->applyRelatedScope(Customer::query(), 'customers', 'bank_accounts')->find($validated['sub_category'] ?? null);
        }

        if ($validated['category'] !== 'self' && !$subCategoryModel) {
            return redirect()->back()->withErrors(['sub_category' => 'Invalid sub category'])->withInput();
        }

        $bankAccount->forceFill([
            'category' => $validated['category'],
            'sub_category_type' => $subCategoryModel ? get_class($subCategoryModel) : null,
            'sub_category_id' => $subCategoryModel?->id,
            'bank_id' => $validated['bank_id'],
            'account_title' => $validated['account_title'],
            'date' => $validated['date'],
            'account_no' => $validated['account_no'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'chqbk_serial_start' => $request->input('cheque_book_serial.start'),
            'chqbk_serial_end' => $request->input('cheque_book_serial.end'),
            'status' => $validated['status'] ?? $bankAccount->status,
        ])->save();

        Cache::forget('category_data:self_account');

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bankAccount, 'bank_accounts');

        $dependencies = $this->dependencyCounts([
            'customer payments' => ['customer_payments', 'bank_account_id', $bankAccount->id],
            'supplier payments' => ['supplier_payments', 'bank_account_id', $bankAccount->id],
            'payment clears' => ['payment_clears', 'bank_account_id', $bankAccount->id],
            'statement adjustments' => fn () => $bankAccount->statementAdjustments()->count(),
            'payment programs' => fn () => $bankAccount->paymentPrograms()->count(),
        ]);

        if ($dependencies !== []) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Bank account', $dependencies));
        }

        DB::transaction(fn () => $bankAccount->delete());

        Cache::forget('category_data:self_account');

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account deleted successfully.');
    }

    public function updateStatus(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin'])) {
            return $resp;
        }

        $bankAccount = BankAccount::findOrFail($request->user_id);
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bankAccount, 'bank_accounts');

        if ($request->status == 'active') {
            $bankAccount->status = 'in_active';
            $bankAccount->save();
        } else {
            $bankAccount->status = 'active';
            $bankAccount->save();
        }
        Cache::forget('category_data:self_account');
        return redirect()->back()->with('success', 'Status has been updated successfully!');
    }

    public function updateSerial(BankAccount $account, Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($account, 'bank_accounts');

        $validator = Validator::make($request->all(), [
            'cheque_book_serial' => 'required|array',
            'cheque_book_serial.start' => 'required|integer|min:1',
            'cheque_book_serial.end' => 'required|integer|gt:cheque_book_serial.start',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $chqbk_serial_start = $request->input('cheque_book_serial.start');
        $chqbk_serial_end = $request->input('cheque_book_serial.end');

        $account->chqbk_serial_start = $chqbk_serial_start;
        $account->chqbk_serial_end = $chqbk_serial_end;
        $account->save();

        Cache::forget('category_data:self_account');
        return redirect()->route('bank-accounts.index')->with('success', 'Serial updated successfully!');
    }
}
