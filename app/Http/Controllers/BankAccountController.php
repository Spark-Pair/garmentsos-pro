<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Setup;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // $bankAccounts = BankAccount::with('subCategory', 'bank')->orderBy('id', 'desc')->get();

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $bankAccounts = BankAccount::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $bankAccounts, 'authLayout' => $authLayout]);
        }

        $bank_options = Setup::where('type', 'bank_name')
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
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $bank_options = [];
        $banks = Setup::where('type', 'bank_name')->get();

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
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

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

        $data = $request->all();

        $subCategoryModel = null;

        // Dynamically associate sub_category based on category
        if ($data['category'] === 'supplier') {
            $subCategoryModel = Supplier::find($data['sub_category']);
        } elseif ($data['category'] === 'customer') {
            $subCategoryModel = Customer::find($data['sub_category']);
        }

        // Ensure subCategoryModel is not null
        if ($data['category'] !== 'self' && !$subCategoryModel) {
            return redirect()->back()->withErrors(['sub_category' => 'Invalid sub category'])->withInput();
        }

        $chqbk_serial_start = $request->input('cheque_book_serial.start');
        $chqbk_serial_end = $request->input('cheque_book_serial.end');

        $bankAccount = new BankAccount([
            'category' => $data['category'],
            'bank_id' => $data['bank_id'],
            'account_title' => $data['account_title'],
            'date' => $data['date'],
            'account_no' => $data['account_no'],
            'chqbk_serial_start' => $chqbk_serial_start,
            'chqbk_serial_end' => $chqbk_serial_end,
        ]);

        if ($subCategoryModel) {
            $subCategoryModel->bankAccounts()->save($bankAccount);
        } else {
            $bankAccount->save(); // Self category ke liye direct save
        }

        return redirect()->route('bank-accounts.create')->with('success', 'Bank account added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BankAccount $bankAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        //
    }

    public function updateStatus(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $bankAccount = BankAccount::find($request->user_id);

        if ($request->status == 'active') {
            $bankAccount->status = 'in_active';
            $bankAccount->save();
        } else {
            $bankAccount->status = 'active';
            $bankAccount->save();
        }
        return redirect()->back()->with('success', 'Status has been updated successfully!');
    }

    public function updateSerial(BankAccount $account, Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'cheque_book_serial' => 'nullable|array',
            'cheque_book_serial.start' => 'nullable|numeric',
            'cheque_book_serial.end' => 'nullable|numeric|gte:cheque_book_serial.start',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $chqbk_serial_start = $request->input('cheque_book_serial.start');
        $chqbk_serial_end = $request->input('cheque_book_serial.end');

        $account->chqbk_serial_start = $chqbk_serial_start;
        $account->chqbk_serial_end = $chqbk_serial_end;
        $account->save();

        return redirect()->route('bank-accounts.index')->with('success', 'Serial updated successfully!');
    }
}
