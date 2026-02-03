<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nette\Schema\Expect;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $vouchers = Voucher::with([
                    'supplier:id,supplier_name',
                    'payments.cheque.customer',
                    'payments.slip.customer',
                    'payments.program.customer',
                    'payments.bankAccount.bank',
                    'payments.selfAccount.bank'
                ])->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $vouchers, 'authLayout' => $authLayout]);
        }

        // // Eager load all relations needed
        // $vouchers = Voucher::with([
        //     'supplier:id,supplier_name',
        //     'payments.cheque.customer',
        //     'payments.slip.customer',
        //     'payments.program.customer',
        //     'payments.bankAccount.bank',
        //     'payments.selfAccount.bank'
        // ])
        // ->orderByDesc('id')
        // ->get();

        // // Preload supplier balances in batch to reduce queries (optional if calculateBalance is query-heavy)
        // $supplierIds = $vouchers->pluck('supplier.id')->filter()->unique();
        // $supplierBalances = [];
        // foreach ($supplierIds as $id) {
        //     $supplierBalances[$id] = Supplier::find($id)->calculateBalance(null, now(), false, false);
        // }

        // foreach ($vouchers as $voucher) {
        //     // Calculate previous balance only if supplier exists
        //     if ($voucher->supplier) {
        //         $voucher->previous_balance = $supplierBalances[$voucher->supplier->id] ?? 0;
        //     }

        //     // Sum of all payments
        //     $voucher->total_payment = $voucher->payments->sum('amount');
        // }

        return view("vouchers.index", compact( "authLayout"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        if ($request->ajax()) {
            $supplier_id = $request->supplier_id;
            $paymentMethod = $request->payment_method;
            $date = $request->date . ' 00:00:00';
            $payments_options = [];

            if ($paymentMethod == 'cheque') {
                $cheques = CustomerPayment::whereNotNull('cheque_no')
                    ->with('customer.city')
                    ->whereDoesntHave('cheque')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $cheques->map(function ($cheque) {
                    return [
                        'id' => (int)$cheque->id,
                        'text' => number_format($cheque->amount) . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                        'dataset' => $cheque->makeHidden('creator'),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'slip') {
                $slips = CustomerPayment::whereNotNull('slip_no')
                    ->with('customer.city')
                    ->whereDoesntHave('slip')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $slips->map(function ($slip) {
                    return [
                        'id' => (int)$slip->id,
                        'text' => number_format($slip->amount) . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                        'dataset' => $slip->makeHidden('creator'),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'purchase_return') {
                $expenses = Expense::where('supplier_id', $supplier_id)
                    ->where('date', '>=', $date)
                    ->with('expenseSetups')
                    ->get();

                $payments_options = $expenses->map(function ($expense) {
                    return [
                        'id' => (int)$expense->id,
                        'text' => number_format($expense->amount) . ' | ' . $expense->reff_no . ' | ' . date('d-M-Y D', strtotime($expense->date)),
                        'dataset' => $expense,
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'program') {
                $payments = SupplierPayment::where('supplier_id', $supplier_id)
                    ->where('method', 'program')
                    ->whereNull('voucher_id')
                    // ->with('program.customer.city')
                    ->get();

                $payments_options = $payments->map(function ($payment) {
                    return [
                        'id' => (int)$payment->id,
                        'text' => number_format($payment->amount) . ' | ' . ($payment->program->customer->customer_name ?? '-')  . ' | ' . ($payment->program->customer->city->short_title ?? '-') . ' | ' . $payment->transaction_id . ' | ' . date('d-M-Y D', strtotime($payment->date)),
                        'dataset' => $payment,
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'self_cheque' || $paymentMethod == 'atm') {
                $self_accounts = BankAccount::where('category', 'self')
                    ->with('bank')
                    ->get()
                    ->makeHidden('creator');

                $payments_options = $self_accounts->map(function ($account) {
                    return [
                        'id' => (int)$account->id,
                        'text' => $account->account_title . ' | ' . $account->bank->short_title,
                        'dataset' => $account,
                    ];
                })->values()->toArray();
            }

            return response()->json(['payments_options' => $payments_options]);
        }

        $voucherType = auth()->user()->voucher_type;

        // --- Last voucher ---
        $last_voucher = Voucher::orderByDesc('id')->first();
        if (!$last_voucher) {
            $last_voucher = (object)['voucher_no' => '00/149'];
        }

        if ($voucherType == 'supplier') {
            // --- Suppliers ---
            $suppliers = Supplier::whereHas('user', fn($q) => $q->where('status', 'active'))->select('id', 'supplier_name', 'date')->get();

            $suppliers_options = $suppliers->mapWithKeys(function ($supplier) {
                return [
                    (int)$supplier->id => [
                        'text' => $supplier->supplier_name,
                        'data_option' => $supplier,
                    ]
                ];
            })->toArray();

            return view("vouchers.create", compact("suppliers_options", 'last_voucher'));
        } else {
            // --- Self Accounts ---
            $self_accounts = BankAccount::where('category', 'self')
                ->with('bank')
                ->get()
                ->makeHidden('creator');

            $self_accounts_options = $self_accounts->mapWithKeys(function ($account) {
                return [
                    (int)$account->id => [
                        'text' => $account->account_title . ' - ' . $account->bank->short_title,
                        'data_option' => $account,
                    ]
                ];
            })->toArray();

            return view("vouchers.create", compact("self_accounts_options", 'last_voucher'));
        }
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
            "supplier_id" => "nullable|integer|exists:suppliers,id",
            "date" => "required|date",
            "program_id" => "nullable|exists:payment_programs,id",
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $voucher = Voucher::create([
            'voucher_no' => $request->voucher_no,
            'supplier_id' => $request->supplier_id,
            'date' => $request->date,
        ]);

        $voucher->save();

        $data = $request->all();

        $paymentDetailsArray = json_decode($data['payment_details_array'], true);

        foreach ($paymentDetailsArray as $paymentDetails) {
            if (isset($paymentDetails['self_account_id'])) {
                if ($paymentDetails['method'] == 'Cash' || $paymentDetails['method'] == 'Adjustment') {
                    CustomerPayment::create([
                        'date' => $request->date,
                        'type' => 'self_account_deposit',
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'remarks' => $paymentDetails['remarks'],
                        'bank_account_id' => $paymentDetails['self_account_id'],
                    ]);

                    SupplierPayment::create([
                        'date' => $request->date,
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'remarks' => $paymentDetails['remarks'],
                        'self_account_id' => $paymentDetails['self_account_id'],
                        'voucher_id' => $voucher->id,
                    ]);
                } else if ($paymentDetails['method'] == 'Cheque') {
                    $customerPayment = CustomerPayment::find($paymentDetails['cheque_id']);
                    if ($customerPayment) {
                        $customerPayment->update([
                            'bank_account_id' => $paymentDetails['self_account_id'],
                            'is_return' => false,
                        ]);

                        SupplierPayment::create([
                            'date' => $request->date,
                            'method' => $paymentDetails['method'],
                            'amount' => $paymentDetails['amount'],
                            'cheque_id' => $paymentDetails['cheque_id'],
                            'remarks' => $paymentDetails['remarks'],
                            'self_account_id' => $paymentDetails['self_account_id'],
                            'voucher_id' => $voucher->id,
                        ]);
                    }
                } else if ($paymentDetails['method'] == 'Slip') {
                    $customerPayment = CustomerPayment::find($paymentDetails['slip_id']);
                    if ($customerPayment) {
                        $customerPayment->update([
                            'bank_account_id' => $paymentDetails['self_account_id'],
                            'is_return' => false,
                        ]);

                        SupplierPayment::create([
                            'date' => $request->date,
                            'method' => $paymentDetails['method'],
                            'amount' => $paymentDetails['amount'],
                            'slip_id' => $paymentDetails['slip_id'],
                            'remarks' => $paymentDetails['remarks'],
                            'self_account_id' => $paymentDetails['self_account_id'],
                            'voucher_id' => $voucher->id,
                        ]);
                    }
                } else if ($paymentDetails['method'] == 'Self Cheque') {
                    CustomerPayment::create([
                        'date' => $request->date,
                        'type' => 'self_account_deposit',
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'cheque_no' => $paymentDetails['cheque_no'],
                        'cheque_date' => $paymentDetails['cheque_date'],
                        'remarks' => $paymentDetails['remarks'],
                        'bank_account_id' => $paymentDetails['self_account_id'],
                    ]);

                    SupplierPayment::create([
                        'date' => $request->date,
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'cheque_no' => $paymentDetails['cheque_no'],
                        'bank_account_id' => $paymentDetails['bank_account_id'],
                        'remarks' => $paymentDetails['remarks'],
                        'self_account_id' => $paymentDetails['self_account_id'],
                        'voucher_id' => $voucher->id,
                    ]);
                } else if ($paymentDetails['method'] == 'ATM') {
                    CustomerPayment::create([
                        'date' => $request->date,
                        'type' => 'self_account_deposit',
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'reff_no' => $paymentDetails['reff_no'],
                        'remarks' => $paymentDetails['remarks'],
                        'bank_account_id' => $paymentDetails['self_account_id'],
                    ]);

                    SupplierPayment::create([
                        'date' => $request->date,
                        'method' => $paymentDetails['method'],
                        'amount' => $paymentDetails['amount'],
                        'reff_no' => $paymentDetails['reff_no'],
                        'bank_account_id' => $paymentDetails['bank_account_id'],
                        'remarks' => $paymentDetails['remarks'],
                        'self_account_id' => $paymentDetails['self_account_id'],
                        'voucher_id' => $voucher->id,
                    ]);
                }
            } else {
                $paymentDetails['supplier_id'] = $request->supplier_id;
                $paymentDetails['date'] = $request->date;
                $paymentDetails['voucher_id'] = $voucher->id;

                if ($paymentDetails['method'] == 'Cheque' || $paymentDetails['method'] == 'Slip') {
                    $customerPayment = CustomerPayment::find($paymentDetails[$paymentDetails['method'] == 'Cheque' ? 'cheque_id' : 'slip_id']);
                    if ($customerPayment) {
                        $customerPayment->update([
                            'bank_account_id' => $paymentDetails['bank_account_id'] ?? null,
                            'is_return' => false,
                        ]);
                    }
                }

                if ($paymentDetails['payment_id'] ?? false) {
                    $payment = SupplierPayment::find($paymentDetails['payment_id']);

                    if ($payment) {
                        $payment->update(['voucher_id' => $voucher->id]);
                    }
                } else {
                    $supplierPayment = SupplierPayment::create($paymentDetails);
                }
            }
        }

        return redirect()->route('vouchers.create')->with('success', 'Voucher Added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Voucher $voucher)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Voucher $voucher)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // $voucher->load([
        //     'supplier' => fn ($q) => $q->with([
        //         'payments' => fn ($q) =>
        //             $q->where('method', 'program')
        //             ->whereNull('voucher_id')
        //             ->with('program.customer.city:id,title'),
        //         'expenses',
        //     ]),
        //     'payments.cheque.customer.city',
        //     'payments.slip.customer.city',
        //     'payments.program.customer.city',
        //     'payments.bankAccount.bank',
        //     'payments.selfAccount.bank',
        // ]);

        $voucher->load([
            'supplier' => fn ($q) => $q->with([
                'payments' => fn ($q) =>
                    $q->where('method', 'program')
                    ->whereNull('voucher_id')
                    ->with('program.customer.city:id,title'),
                'expenses',
            ]),
            'payments.cheque' => fn($q) => $q->whereDoesntHave('paymentClearRecord'),
            'payments.cheque.customer.city',
            'payments.slip' => fn($q) => $q->whereDoesntHave('paymentClearRecord'),
            'payments.slip.customer.city',
            'payments.program.customer.city',
            'payments.bankAccount.bank',
            'payments.selfAccount.bank',
        ]);

        $cheques = CustomerPayment::whereNotNull('cheque_no')->with('customer.city')->whereDoesntHave('cheque')->whereNull('bank_account_id')->get();
        $cheques_options = [];

        foreach ($cheques as $cheque) {
            $cheques_options[(int)$cheque->id] = [
                'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                'data_option' => $cheque->makeHidden('creator'),
            ];
        }

        $slips = CustomerPayment::whereNotNull('slip_no')->with('customer.city')->whereDoesntHave('slip')->whereNull('bank_account_id')->get();
        $slips_options = [];

        foreach ($slips as $slip) {
            $slips_options[(int)$slip->id] = [
                'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                'data_option' => $slip->makeHidden('creator'),
            ];
        }

        $self_accounts = BankAccount::where('category', 'self')->with('bank')->get()->makeHidden('creator');

        $self_accounts_options = [];

        foreach ($self_accounts as $account) {
            $self_accounts_options[(int)$account->id] = [
                'text' => $account->account_title . ' - ' . $account->bank->short_title,
                'data_option' => $account,
            ];
        }

        if ($voucher->supplier_id === null && Auth::user()->voucher_type == 'supplier') {
            $user = Auth::user();
            $user->voucher_type = 'self_account';
            $user->save();
        } else if ($voucher->supplier_id !== null && Auth::user()->voucher_type == 'self_account') {
            $user = Auth::user();
            $user->voucher_type = 'supplier';
            $user->save();
        }

        return view("vouchers.edit", compact('voucher', 'cheques_options', 'slips_options', 'self_accounts', 'self_accounts_options'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Voucher $voucher)
    {
        // -----------------------------
        // Step 1: Authorization check
        // -----------------------------
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        // -----------------------------
        // Step 2: Validation
        // -----------------------------
        $validator = Validator::make($request->all(), [
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', $validator->errors()->first());
        }

        $requestPayments = json_decode($request->payment_details_array, true);

        // return $requestPayments;

        DB::transaction(function () use ($voucher, $requestPayments) {

            // -----------------------------
            // Step 3: Delete all existing payments
            // -----------------------------
            $existingPayments = $voucher->payments()->get();

            foreach ($existingPayments as $old) {
                // Detach or update related CustomerPayment
                if ($old->method === "Cheque" && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->update([
                        'bank_account_id' => null,
                        'is_return' => false,
                    ]);
                }

                if ($old->method === "Slip" && $old->slip_id) {
                    CustomerPayment::where('id', $old->slip_id)->update([
                        'bank_account_id' => null,
                        'is_return' => false,
                    ]);
                }

                if ($voucher->supplier_id === null && in_array($old->method, ["Cash", "Adjustment", "Self Cheque", "ATM"]) && !empty($old->self_account_id)) {
                    CustomerPayment::where([
                        'date' => $old->date,
                        'type' => 'self_account_deposit',
                        'method' => $old->method,
                        'amount' => $old->amount,
                        'bank_account_id' => $old->self_account_id,
                    ])->delete();
                }

                // For Program method, just detach
                if ($old->method === "program" || $old->method === "Program") {
                    $old->update(['voucher_id' => null]);
                } else {
                    $old->delete();
                }
            }

            // -----------------------------
            // Step 4: Add new payments
            // -----------------------------
            foreach ($requestPayments as $pd) {

                // Validate unique cheque_no
                Validator::make($pd, [
                    'cheque_no' => ['nullable', Rule::unique('supplier_payments', 'cheque_no')],
                ])->validate();

                $pd['supplier_id'] = $voucher->supplier_id;
                $pd['voucher_id'] = $voucher->id;
                $pd['date'] = $voucher->date;

                if ($pd['method'] == 'program') {
                    $supplierPayment = SupplierPayment::find($pd['payment_id'] ?? $pd['id']);
                    if ($supplierPayment) {
                        $supplierPayment->update(['voucher_id' => $pd['voucher_id']]);
                    }
                } else {
                    $newPayment = SupplierPayment::create(attributes: $pd);
                }

                // Self Account logic
                if (!empty($pd['self_account_id'])) {
                    $cpBase = [
                        'date' => $pd['date'],
                        'type' => 'self_account_deposit',
                        'method' => $pd['method'],
                        'amount' => $pd['amount'],
                        'remarks' => $pd['remarks'] ?? null,
                        'bank_account_id' => $pd['self_account_id'],
                    ];

                    if (in_array($pd['method'], ['Cash', 'Adjustment'])) {
                        CustomerPayment::create($cpBase);
                    }

                    if ($pd['method'] === "Self Cheque") {
                        CustomerPayment::create(array_merge($cpBase, [
                            'cheque_no' => $pd['cheque_no'],
                            'cheque_date' => $pd['cheque_date'],
                        ]));
                    }

                    if ($pd['method'] === "ATM") {
                        CustomerPayment::create(array_merge($cpBase, [
                            'reff_no' => $pd['reff_no'],
                        ]));
                    }
                }
            }

        }); // End transaction

        return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Voucher $voucher)
    {
        //
    }
}
