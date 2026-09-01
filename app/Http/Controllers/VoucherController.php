<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Voucher;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Nette\Schema\Expect;

class VoucherController extends Controller
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

        if ($request->ajax()) {
            $branches = app(ModuleBranchService::class);
            $vouchers = Voucher::with([
                    'branch',
                    'supplier:id,supplier_name',
                    'payments.cheque.customer',
                    'payments.slip.customer',
                    'payments.program.customer',
                    'payments.bankAccount.bank',
                    'payments.selfAccount.bank'
                ])->orderByDesc('id');

            if ($branches->shouldFilterRecords('vouchers')) {
                $vouchers = $branches->applyScope($vouchers, 'vouchers');
            }

            $vouchers = $vouchers->applyFilters($request);

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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        if ($request->ajax()) {
            $supplier_id = $request->supplier_id;
            $paymentMethod = $request->payment_method;
            $date = $request->date;
            $payments_options = [];
            $supplierBalanceAtDate = 0;

            if ($supplier_id && $request->date) {
                $supplier = Supplier::find($supplier_id);
                if ($supplier) {
                    $supplierBalanceAtDate = $this->supplierBalance($supplier, $request->date);
                }
            }

            if ($paymentMethod == 'cheque') {
                $cheques = app(ModuleBranchService::class)
                    ->applyRelatedScope(CustomerPayment::whereNotNull('cheque_no'), 'customer_payments', 'vouchers')
                    ->with('customer.city')
                    ->whereDoesntHave('cheque')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $cheques->map(function ($cheque) {
                    return [
                        'id' => (int)$cheque->id,
                        'text' => \App\Support\Money::format($cheque->amount) . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                        'dataset' => $this->formatVoucherCustomerPaymentPayload($cheque),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'slip') {
                $slips = app(ModuleBranchService::class)
                    ->applyRelatedScope(CustomerPayment::whereNotNull('slip_no'), 'customer_payments', 'vouchers')
                    ->with('customer.city')
                    ->whereDoesntHave('slip')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $slips->map(function ($slip) {
                    return [
                        'id' => (int)$slip->id,
                        'text' => \App\Support\Money::format($slip->amount) . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                        'dataset' => $this->formatVoucherCustomerPaymentPayload($slip),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'purchase_return') {
                $expenses = Expense::where('supplier_id', $supplier_id)
                    ->whereDate('date', '>=', $date)
                    ->with('expenseSetups')
                    ->get();

                $payments_options = $expenses->map(function ($expense) {
                    return [
                        'id' => (int)$expense->id,
                        'text' => \App\Support\Money::format($expense->amount) . ' | ' . $expense->reff_no . ' | ' . date('d-M-Y D', strtotime($expense->date)),
                        'dataset' => $this->formatVoucherExpensePayload($expense),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod === 'program') {
                $payments = SupplierPayment::where('supplier_id', $supplier_id)
                    ->whereRaw('LOWER(method) LIKE ?', ['%program%'])
                    ->whereNull('voucher_id')
                    ->select(
                        'id',
                        'program_id',
                        'amount',
                        'transaction_id',
                        'date'
                    )
                    ->with([
                        'program:id,customer_id',
                        'program.customer:id,customer_name,city_id',
                        'program.customer.city:id,short_title',
                    ])
                    ->get();

                $payments_options = $payments->map(function ($payment) {
                    return [
                        'id' => (int)$payment->id,
                        'text' =>
                            \App\Support\Money::format($payment->amount) . ' | ' .
                            ($payment->program?->customer?->customer_name ?? '-') . ' | ' .
                            ($payment->program?->customer?->city?->short_title ?? '-') . ' | ' .
                            ($payment->transaction_id ?? '-') . ' | ' .
                            optional($payment->date)->format('d-M-Y D'),
                        'dataset' => $this->formatVoucherSupplierPaymentPayload($payment),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'self_cheque' || $paymentMethod == 'atm') {
                $self_accounts = app(ModuleBranchService::class)->applyRelatedScope(BankAccount::where('category', 'self'), 'bank_accounts', 'vouchers')
                    ->with('bank')
                    ->get()
                    ->makeHidden('creator');

                $payments_options = $self_accounts->map(function ($account) {
                    return [
                        'id' => (int)$account->id,
                        'text' => $account->account_title . ' | ' . $account->bank->short_title,
                        'dataset' => $this->formatVoucherBankAccountPayload($account),
                    ];
                })->values()->toArray();
            }

            return response()->json([
                'payments_options' => $payments_options,
                'supplier_balance_at_date' => $supplierBalanceAtDate,
            ]);
        }

        $voucherType = auth()->user()->voucher_type;

        // --- Last voucher ---
        $branches = app(ModuleBranchService::class);
        $lastVoucherQuery = Voucher::orderByDesc('id');
        if ($branches->shouldFilterRecords('vouchers')) {
            $lastVoucherQuery = $branches->applyScope($lastVoucherQuery, 'vouchers');
        }
        $last_voucher = $lastVoucherQuery->first();
        if (!$last_voucher) {
            $last_voucher = (object)['voucher_no' => '00/149'];
        }
        if (app(ModuleBranchService::class)->shouldFilterRecords('vouchers')) {
            $last_voucher = (object) ['voucher_no' => app(BranchSerialService::class)->next('vouchers', Voucher::class, 'voucher_no', 'V')];
        }
        $nextVoucherNo = app(BranchSerialService::class)->next('vouchers', Voucher::class, 'voucher_no', 'V');

        // --- Self Accounts (needed for Self Cheque / ATM even in supplier vouchers) ---
        $self_accounts = app(ModuleBranchService::class)->applyRelatedScope(BankAccount::where('category', 'self'), 'bank_accounts', 'vouchers')
            ->with('bank')
            ->get()
            ->makeHidden('creator');

        $bankAccountPayloads = $this->bankAccountOptionPayloads($self_accounts, 'vouchers');
        $self_accounts_options = $self_accounts->mapWithKeys(function ($account) use ($bankAccountPayloads) {
            return [
                (int)$account->id => [
                    'text' => $account->account_title . ' - ' . $account->bank->short_title,
                    'data_option' => $this->formatVoucherBankAccountPayload($account, $bankAccountPayloads[(int) $account->id]),
                ]
            ];
        })->toArray();

        if ($voucherType == 'supplier') {
            // --- Suppliers ---
            $suppliers = app(ModuleBranchService::class)
                ->applyRelatedScope(Supplier::whereHas('user', fn($q) => $q->where('status', 'active')), 'suppliers', 'vouchers')
                ->select('id', 'supplier_name', 'date')
                ->get();

            $supplierPayloads = $this->supplierOptionPayloads($suppliers, 'vouchers');
            $suppliers_options = $suppliers->mapWithKeys(function ($supplier) use ($supplierPayloads) {
                return [
                    (int)$supplier->id => [
                        'text' => $supplier->supplier_name,
                        'data_option' => $supplierPayloads[(int) $supplier->id],
                    ]
                ];
            })->toArray();

            $branchBranding = app(ModuleBranchService::class)->documentBranding('vouchers');
            return view("vouchers.create", compact("suppliers_options", 'self_accounts_options', 'last_voucher', 'branchBranding', 'nextVoucherNo'));
        } else {
            $branchBranding = app(ModuleBranchService::class)->documentBranding('vouchers');
            return view("vouchers.create", compact("self_accounts_options", 'last_voucher', 'branchBranding', 'nextVoucherNo'));
        }
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
            "supplier_id" => "nullable|integer|exists:suppliers,id",
            "date" => "required|date",
            "program_id" => "nullable|exists:payment_programs,id",
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }

        $paymentDetailsArray = json_decode($request->payment_details_array, true) ?? [];
        $this->assertVoucherPaymentsAreUnique($paymentDetailsArray);
        $this->assertSelfVoucherPaymentsAreComplete($paymentDetailsArray, empty($request->supplier_id));

        DB::transaction(function () use ($request, $paymentDetailsArray) {
            $branches = app(ModuleBranchService::class);
            $voucherNo = app(BranchSerialService::class)->next('vouchers', Voucher::class, 'voucher_no', 'V');

            $voucher = Voucher::create([
                'voucher_no' => $voucherNo,
                'supplier_id' => $request->supplier_id,
                'date' => $request->date,
                'branch_id' => $branches->branchIdForCreate('vouchers'),
            ]);
            $voucherBranchId = $voucher->branch_id ?: $branches->branchIdForCreate('vouchers');

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
                            'branch_id' => $voucherBranchId,
                        ]);

                        SupplierPayment::create([
                            'date' => $request->date,
                            'method' => $paymentDetails['method'],
                            'amount' => $paymentDetails['amount'],
                            'remarks' => $paymentDetails['remarks'],
                            'self_account_id' => $paymentDetails['self_account_id'],
                            'voucher_id' => $voucher->id,
                            'branch_id' => $voucherBranchId,
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
                                'branch_id' => $voucherBranchId,
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
                                'branch_id' => $voucherBranchId,
                            ]);
                        }
                    } else if ($paymentDetails['method'] == 'Self Cheque') {
                        $customerPayment = CustomerPayment::create([
                            'date'           => $request->date,
                            'type'           => 'self_account_deposit',
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'cheque_no'      => $paymentDetails['cheque_no'],
                            'cheque_date'    => $paymentDetails['cheque_date'],
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['self_account_id'],
                            'branch_id'      => $voucherBranchId,
                        ]);

                        SupplierPayment::create([
                            'date'           => $request->date,
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'cheque_id'      => $customerPayment->id, // link, not duplicate
                            'cheque_no'      => $paymentDetails['cheque_no'],
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['bank_account_id'],
                            'self_account_id'=> $paymentDetails['self_account_id'],
                            'voucher_id'     => $voucher->id,
                            'branch_id'      => $voucherBranchId,
                        ]);
                    } else if ($paymentDetails['method'] == 'ATM') {
                        $customerPayment = CustomerPayment::create([
                            'date'           => $request->date,
                            'type'           => 'self_account_deposit',
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'reff_no'        => $paymentDetails['reff_no'],
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['self_account_id'],
                            'branch_id'      => $voucherBranchId,
                        ]);

                        SupplierPayment::create([
                            'date'           => $request->date,
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'cheque_id'      => $customerPayment->id,
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['bank_account_id'],
                            'self_account_id'=> $paymentDetails['self_account_id'],
                            'voucher_id'     => $voucher->id,
                            'branch_id'      => $voucherBranchId,
                        ]);
                    }
                } else {
                    $paymentDetails['supplier_id'] = $request->supplier_id;
                    $paymentDetails['date'] = $request->date;
                    $paymentDetails['voucher_id'] = $voucher->id;
                    $paymentDetails['branch_id'] = $voucherBranchId;

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
                        SupplierPayment::create($paymentDetails);
                    }
                }
            }
        });

        return redirect()->route('vouchers.create')->with('success', 'Voucher Added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Voucher $voucher)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($voucher, 'vouchers');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Voucher $voucher)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($voucher, 'vouchers');

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
                    $q->whereRaw('LOWER(method) LIKE ?', ['%program%'])
                    ->whereNull('voucher_id')
                    ->with('program.customer.city:id,title'),
                'expenses',
            ]),
            // Existing voucher rows must always be hydrated on edit. Clearance
            // affects whether a payment can be reused, not whether its saved
            // details are visible on the voucher that already owns it.
            'payments.cheque',
            'payments.cheque.customer.city',
            'payments.slip',
            'payments.slip.customer.city',
            'payments.program.customer.city',
            'payments.bankAccount.bank',
            'payments.selfAccount.bank',
        ]);

        if ($voucher->supplier && $voucher->date) {
            $voucher->supplier->balance_at_date = $this->supplierBalance($voucher->supplier, $voucher->date);
        }

        $branches = app(ModuleBranchService::class);
        $cheques = $branches->applyRelatedScope(CustomerPayment::whereNotNull('cheque_no'), 'customer_payments', 'vouchers')
            ->with('customer.city')
            ->whereDoesntHave('cheque')
            ->whereNull('bank_account_id')
            ->get();
        $cheques_options = [];

        foreach ($cheques as $cheque) {
            $chequePayload = $this->formatVoucherCustomerPaymentPayload($cheque);
            $cheques_options[(int)$cheque->id] = [
                'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                'data_option' => $chequePayload,
            ];
        }

        $slips = $branches->applyRelatedScope(CustomerPayment::whereNotNull('slip_no'), 'customer_payments', 'vouchers')
            ->with('customer.city')
            ->whereDoesntHave('slip')
            ->whereNull('bank_account_id')
            ->get();
        $slips_options = [];

        foreach ($slips as $slip) {
            $slipPayload = $this->formatVoucherCustomerPaymentPayload($slip);
            $slips_options[(int)$slip->id] = [
                'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                'data_option' => $slipPayload,
            ];
        }

        $self_accounts = app(ModuleBranchService::class)->applyRelatedScope(BankAccount::where('category', 'self')->with('bank'), 'bank_accounts', 'vouchers')->get();
        $selfAccountsPayload = $self_accounts
            ->map(fn ($account) => $this->formatVoucherBankAccountPayload($account))
            ->values()
            ->all();

        $self_accounts_options = [];

        foreach ($self_accounts as $account) {
            $accountPayload = $this->formatVoucherBankAccountPayload($account);
            $self_accounts_options[(int)$account->id] = [
                'text' => $account->account_title . ' - ' . $account->bank->short_title,
                'data_option' => $accountPayload,
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

        $branchBranding = app(ModuleBranchService::class)->documentBranding('vouchers', $voucher);
        $voucherPayload = $this->formatVoucherEditPayload($voucher);

        return view("vouchers.edit", compact('voucher', 'voucherPayload', 'cheques_options', 'slips_options', 'selfAccountsPayload', 'self_accounts_options', 'branchBranding'));
    }

    private function formatVoucherEditPayload(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'branch_id' => $voucher->branch_id,
            'branch_branding' => app(ModuleBranchService::class)->documentBranding('vouchers', $voucher),
            'supplier_id' => $voucher->supplier_id,
            'date' => $voucher->date?->format('Y-m-d'),
            'supplier' => $voucher->supplier ? $this->formatVoucherSupplierPayload($voucher->supplier) : null,
            'payments' => $voucher->payments
                ? $voucher->payments->map(fn ($payment) => $this->formatVoucherSupplierPaymentPayload($payment))->values()->all()
                : [],
        ];
    }

    private function formatVoucherSupplierPayload(Supplier $supplier): array
    {
        $balance = (float) ($supplier->balance_at_date ?? $this->supplierBalance($supplier));
        $payload = $this->supplierOptionPayload($supplier);
        $payload['balance'] = $balance;
        $payload['balance_formatted'] = \App\Support\Money::format($balance);
        $payload['balance_at_date'] = $balance;
        $payload['balance_at_date_formatted'] = \App\Support\Money::format($balance);

        return $payload + [
            'payments' => $supplier->payments
                ? $supplier->payments->map(fn ($payment) => $this->formatVoucherSupplierPaymentPayload($payment))->values()->all()
                : [],
            'expenses' => $supplier->expenses
                ? $supplier->expenses->map(fn ($expense) => $this->formatVoucherExpensePayload($expense))->values()->all()
                : [],
        ];
    }

    private function formatVoucherSupplierPaymentPayload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_id' => $payment->id,
            'supplier_id' => $payment->supplier_id,
            'voucher_id' => $payment->voucher_id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'amount' => (float) ($payment->amount ?? 0),
            'reff_no' => $payment->reff_no,
            'cheque_no' => $payment->cheque_no,
            'cheque_id' => $payment->cheque_id,
            'slip_id' => $payment->slip_id,
            'program_id' => $payment->program_id,
            'bank_account_id' => $payment->bank_account_id,
            'self_account_id' => $payment->self_account_id,
            'transaction_id' => $payment->transaction_id,
            'remarks' => $payment->remarks,
            'cheque' => $payment->cheque ? $this->formatVoucherCustomerPaymentPayload($payment->cheque) : null,
            'slip' => $payment->slip ? $this->formatVoucherCustomerPaymentPayload($payment->slip) : null,
            'program' => $payment->program ? $this->formatVoucherPaymentProgramPayload($payment->program) : null,
            'bank_account' => $payment->bankAccount ? $this->formatVoucherBankAccountPayload($payment->bankAccount) : null,
            'self_account' => $payment->selfAccount ? $this->formatVoucherBankAccountPayload($payment->selfAccount) : null,
        ];
    }

    private function formatVoucherCustomerPaymentPayload(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'type' => $payment->type,
            'amount' => (float) ($payment->amount ?? 0),
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'reff_no' => $payment->reff_no,
            'transaction_id' => $payment->transaction_id,
            'cheque_date' => $payment->cheque_date?->format('Y-m-d'),
            'slip_date' => $payment->slip_date?->format('Y-m-d'),
            'remarks' => $payment->remarks,
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'customer_name' => $payment->customer->customer_name,
                'city' => $payment->customer->city ? [
                    'id' => $payment->customer->city->id,
                    'title' => $payment->customer->city->title,
                    'short_title' => $payment->customer->city->short_title,
                ] : null,
            ] : null,
        ];
    }

    private function formatVoucherPaymentProgramPayload($program): array
    {
        return [
            'id' => $program->id,
            'program_no' => $program->program_no,
            'customer_id' => $program->customer_id,
            'date' => optional($program->date)->toJSON(),
            'customer' => $program->customer ? [
                'id' => $program->customer->id,
                'customer_name' => $program->customer->customer_name,
                'city' => $program->customer->city ? [
                    'id' => $program->customer->city->id,
                    'title' => $program->customer->city->title,
                    'short_title' => $program->customer->city->short_title,
                ] : null,
            ] : null,
        ];
    }

    private function formatVoucherBankAccountPayload(BankAccount $account, ?array $accountPayload = null): array
    {
        return array_merge($accountPayload ?? $this->bankAccountOptionPayload($account), [
            'date' => optional($account->date)->toJSON(),
            'available_cheques' => $account->available_cheques ?? [],
            'display_label' => $this->selfAccountDisplayLabel($account->account_title),
        ]);
    }

    private function formatVoucherExpensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'date' => optional($expense->date)->toJSON(),
            'supplier_id' => $expense->supplier_id,
            'expense' => $expense->expense,
            'reff_no' => $expense->reff_no,
            'amount' => (float) ($expense->amount ?? 0),
            'lot_no' => $expense->lot_no,
            'remarks' => $expense->remarks,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Voucher $voucher)
    {
        // -----------------------------
        // Step 1: Authorization check
        // -----------------------------
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($voucher, 'vouchers');

        // -----------------------------
        // Step 2: Validation
        // -----------------------------
        $validator = Validator::make($request->all(), [
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }

        $requestPayments = json_decode($request->payment_details_array, true);
        $this->assertVoucherPaymentsAreUnique($requestPayments ?? [], $voucher);
        $this->assertSelfVoucherPaymentsAreComplete($requestPayments ?? [], !$voucher->supplier_id);

        DB::transaction(function () use ($voucher, $requestPayments) {

            // -----------------------------
            // Step 3: Delete existing payments
            // -----------------------------
            $existingPayments = $voucher->payments()->get();

            foreach ($existingPayments as $old) {
                
                if (in_array($old->method, ['Cheque', 'cheque']) && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->update([
                        'bank_account_id' => null,
                        'is_return'       => false,
                    ]);
                }

                if (in_array($old->method, ['Slip', 'slip']) && $old->slip_id) {
                    CustomerPayment::where('id', $old->slip_id)->update([
                        'bank_account_id' => null,
                        'is_return'       => false,
                    ]);
                }

                // Self Cheque — CustomerPayment cheque_id se linked hai, direct delete karo
                if ($old->method === 'Self Cheque' && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->delete();
                }

                // ATM self deposit is linked through cheque_id because supplier_payments has no atm_id column.
                if ($old->method === 'ATM' && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->delete();
                }

                // Cash / Adjustment self deposit — cheque_id nahi hota, fields se match karo
                if (in_array($old->method, ['Cash', 'Adjustment']) && !empty($old->self_account_id)) {
                    $selfDeposit = CustomerPayment::where([
                        'type'           => 'self_account_deposit',
                        'method'         => $old->method,
                        'amount'         => $old->amount,
                        'bank_account_id'=> $old->self_account_id,
                    ])
                        ->whereDate('date', $old->date)
                        ->where('remarks', $old->remarks)
                        ->latest('id')
                        ->first();

                    $selfDeposit?->delete();
                }

                if (in_array($old->method, ['program', 'Program'])) {
                    $old->update(['voucher_id' => null]);
                } else {
                    $old->delete();
                }
            }

            // -----------------------------
            // Step 4: Re-create payments
            // -----------------------------
            foreach ($requestPayments as $pd) {

                $pd['supplier_id'] = $voucher->supplier_id;
                $pd['voucher_id']  = $voucher->id;
                $pd['date']        = $voucher->date;
                $pd['branch_id']   = $voucher->branch_id ?: app(ModuleBranchService::class)->branchIdForCreate('vouchers');

                if (in_array($pd['method'], ['program', 'Program'])) {
                    $supplierPayment = SupplierPayment::find($pd['payment_id'] ?? $pd['id'] ?? null);
                    if ($supplierPayment) {
                        $supplierPayment->update(['voucher_id' => $pd['voucher_id']]);
                    }
                    continue;
                }

                // Self account flows — CustomerPayment pehle banao, phir link karo
                if (!empty($pd['self_account_id'])) {

                    $cpBase = [
                        'date'           => $pd['date'],
                        'type'           => 'self_account_deposit',
                        'method'         => $pd['method'],
                        'amount'         => $pd['amount'],
                        'remarks'        => $pd['remarks'] ?? null,
                        'bank_account_id'=> $pd['self_account_id'],
                        'branch_id'      => $pd['branch_id'],
                    ];

                    if (in_array($pd['method'], ['Cash', 'Adjustment'])) {
                        CustomerPayment::create($cpBase);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Cheque') {
                        CustomerPayment::where('id', $pd['cheque_id'])->update([
                            'is_return'       => false,
                            'bank_account_id' => $pd['self_account_id'],
                        ]);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Slip') {
                        CustomerPayment::where('id', $pd['slip_id'])->update([
                            'is_return'       => false,
                            'bank_account_id' => $pd['self_account_id'],
                        ]);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Self Cheque') {
                        $cp = CustomerPayment::create(array_merge($cpBase, [
                            'cheque_no'   => $pd['cheque_no'] ?? $pd['cheque']['cheque_no'] ?? null,
                            'cheque_date' => $pd['cheque_date'] ?? $pd['cheque']['cheque_date'] ?? null,
                            'bank_account_id' => $pd['self_account_id'],
                        ]));

                        SupplierPayment::create(array_merge($pd, [
                            'cheque_id' => $cp->id,
                            'cheque_no' => $pd['cheque_no'] ?? $pd['cheque']['cheque_no'] ?? null,
                            'bank_account_id' => $pd['bank_account_id'],
                            'self_account_id' => $pd['self_account_id'],
                        ]));        
                    }

                    if ($pd['method'] === 'ATM') {
                        $cp = CustomerPayment::create(array_merge($cpBase, [
                            'reff_no' => $pd['reff_no'] ?? null, // fix
                            'bank_account_id' => $pd['self_account_id'],
                        ]));

                        SupplierPayment::create(array_merge($pd, [
                            'cheque_id'  => $cp->id,
                            'reff_no' => null,
                            'bank_account_id' => $pd['bank_account_id'],
                            'self_account_id' => $pd['self_account_id'],
                        ]));
                    }

                } else {
                    // Normal supplier payment (non-self-account)
                    if (in_array($pd['method'], ['Cheque', 'Slip'])) {
                        $linkKey = $pd['method'] === 'Cheque' ? 'cheque_id' : 'slip_id';
                        if (!empty($pd[$linkKey])) {
                            CustomerPayment::where('id', $pd[$linkKey])->update([
                                'bank_account_id' => $pd['bank_account_id'] ?? null,
                                'is_return'       => false,
                            ]);
                        }
                    }

                    if (!empty($pd['payment_id'])) {
                        $existing = SupplierPayment::find($pd['payment_id']);
                        if ($existing) {
                            $existing->update(['voucher_id' => $voucher->id]);
                        } else {
                            SupplierPayment::create($pd);
                        }
                    } else {
                        SupplierPayment::create($pd);
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
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($voucher, 'vouchers');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $payments = $voucher->payments()->get();

        // Block if any payment under this voucher is already tied to a Credit Return
        if ($payments->contains(fn ($payment) => (bool) $payment->c_r_id)) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Voucher', ['credit return' => 1]));
        }

        DB::transaction(function () use ($voucher, $payments) {
            foreach ($payments as $payment) {
                $method = $this->normalizeVoucherPaymentMethod($payment->method);

                if ($method === 'cheque' && $payment->cheque_id) {
                    CustomerPayment::where('id', $payment->cheque_id)->update([
                        'bank_account_id' => null,
                        'is_return' => false,
                    ]);
                } elseif ($method === 'slip' && $payment->slip_id) {
                    CustomerPayment::where('id', $payment->slip_id)->update([
                        'bank_account_id' => null,
                        'is_return' => false,
                    ]);
                } elseif (in_array($method, ['selfcheque', 'atm']) && $payment->cheque_id) {
                    CustomerPayment::where('id', $payment->cheque_id)
                        ->where('type', 'self_account_deposit')
                        ->delete();
                } elseif (in_array($method, ['cash', 'adjustment']) && $payment->self_account_id) {
                    CustomerPayment::where([
                        'type' => 'self_account_deposit',
                        'method' => $payment->method,
                        'amount' => $payment->amount,
                        'bank_account_id' => $payment->self_account_id,
                    ])
                        ->whereDate('date', $payment->date)
                        ->where('remarks', $payment->remarks)
                        ->latest('id')
                        ->first()
                        ?->delete();
                }

                // Program payments predate the voucher → just unlink, don't delete
                if ($method === 'program') {
                    $payment->update(['voucher_id' => null]);
                } else {
                    $payment->delete();
                }
            }

            $voucher->delete();
        });

        return redirect()->route('vouchers.index')->with('success', 'Voucher deleted successfully.');
    }

    private function assertVoucherPaymentsAreUnique(array $paymentDetailsArray, ?Voucher $currentVoucher = null): void
    {
        $seen = [
            'cheque_id' => [],
            'slip_id' => [],
            'program_payment_id' => [],
            'expense_id' => [],
            'cheque_no' => [],
        ];

        $currentVoucherId = $currentVoucher?->id;

        foreach ($paymentDetailsArray as $index => $paymentDetails) {
            $rowNumber = $index + 1;
            $method = $this->normalizeVoucherPaymentMethod($paymentDetails['method'] ?? '');

            foreach (['cheque_id', 'slip_id', 'expense_id'] as $key) {
                $value = (int) ($paymentDetails[$key] ?? 0);
                if ($value <= 0) {
                    continue;
                }

                if (isset($seen[$key][$value])) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Payment row {$rowNumber} mein duplicate selection allowed nahi hai.",
                    ]);
                }

                $seen[$key][$value] = true;
            }

            if ($method === 'program') {
                $paymentId = (int) ($paymentDetails['payment_id'] ?? $paymentDetails['id'] ?? 0);
                if ($paymentId > 0) {
                    if (isset($seen['program_payment_id'][$paymentId])) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Payment row {$rowNumber} mein same program payment dobara select hui hai.",
                        ]);
                    }

                    $seen['program_payment_id'][$paymentId] = true;

                    $programPayment = SupplierPayment::find($paymentId);
                    if (!$programPayment || ($programPayment->voucher_id && $programPayment->voucher_id !== $currentVoucherId)) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Payment row {$rowNumber} wali program payment pehle se kisi aur voucher ke saath linked hai.",
                        ]);
                    }
                }
            }

            if ($method === 'selfcheque') {
                $chequeNo = trim((string) ($paymentDetails['cheque_no'] ?? ''));
                $bankAccountId = (int) ($paymentDetails['bank_account_id'] ?? 0);

                if ($chequeNo !== '' && $bankAccountId > 0) {

                    // Same cheque number + same bank account
                    // can only be used once within this request.
                    $seenKey = $bankAccountId . ':' . $chequeNo;

                    if (isset($seen['cheque_no'][$seenKey])) {
                        throw ValidationException::withMessages([
                            'payment_details_array' =>
                                "Payment row {$rowNumber} mein cheque no. {$chequeNo} "
                                . "same bank account mein dobara use nahi ho sakta.",
                        ]);
                    }

                    $seen['cheque_no'][$seenKey] = true;

                    // Check database:
                    // Same cheque number + same bank account = duplicate.
                    // Same cheque number in another bank account = allowed.
                    $chequeExists = SupplierPayment::query()
                        ->where('bank_account_id', $bankAccountId)
                        ->where(function ($query) use ($chequeNo) {
                            $query->where('cheque_no', $chequeNo)
                                ->orWhereHas('cheque', function ($cheque) use ($chequeNo) {
                                    $cheque->where('cheque_no', $chequeNo);
                                });
                        })
                        ->when(
                            $currentVoucherId,
                            fn ($q) => $q->where('voucher_id', '!=', $currentVoucherId)
                        )
                        ->exists();

                    if ($chequeExists) {
                        throw ValidationException::withMessages([
                            'payment_details_array' =>
                                "Cheque no. {$chequeNo} is bank account mein pehle se use ho chuka hai.",
                        ]);
                    }
                }
            }

            foreach (['cheque_id', 'slip_id'] as $paymentKey) {
                $paymentId = (int) ($paymentDetails[$paymentKey] ?? 0);
                if ($paymentId <= 0) {
                    continue;
                }

                $linkedExists = SupplierPayment::query()
                    ->where($paymentKey, $paymentId)
                    ->where('is_return', false) 
                    ->when($currentVoucherId, fn($q) => $q->where('voucher_id', '!=', $currentVoucherId))
                    ->exists();

                if ($linkedExists) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Payment row {$rowNumber} wali selected " . str_replace('_', ' ', $paymentKey) . " pehle se kisi aur supplier payment mein use ho chuki hai.",
                    ]);
                }
            }
        }
    }

    private function assertSelfVoucherPaymentsAreComplete(array $payments, bool $isSelfVoucher): void
    {
        if (!$isSelfVoucher) {
            return;
        }

        if ($payments === []) {
            throw ValidationException::withMessages([
                'payment_details_array' => 'Self voucher mein kam az kam aik payment required hai.',
            ]);
        }

        foreach ($payments as $index => $payment) {
            $row = $index + 1;
            $method = $this->normalizeVoucherPaymentMethod($payment['method'] ?? '');
            $amount = (float) ($payment['amount'] ?? 0);
            $sourceAccountId = (int) ($payment['bank_account_id'] ?? 0);
            $destinationAccountId = (int) ($payment['self_account_id'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} mein valid amount required hai.",
                ]);
            }

            if ($destinationAccountId <= 0) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} mein To Account required hai.",
                ]);
            }

            $destinationExists = BankAccount::query()
                ->whereKey($destinationAccountId)
                ->where('category', 'self')
                ->exists();

            if (!$destinationExists) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} ka To Account valid self account nahi hai.",
                ]);
            }

            if (in_array($method, ['selfcheque', 'atm'], true)) {
                if ($sourceAccountId <= 0) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Self voucher row {$row} mein From Account required hai.",
                    ]);
                }

                $sourceExists = BankAccount::query()
                    ->whereKey($sourceAccountId)
                    ->where('category', 'self')
                    ->exists();

                if (!$sourceExists) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Self voucher row {$row} ka From Account valid self account nahi hai.",
                    ]);
                }

                if ($sourceAccountId === $destinationAccountId) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Self voucher row {$row} mein From aur To Account different hone chahiye.",
                    ]);
                }
            }

            if ($method === 'selfcheque') {
                if (!filled($payment['cheque_no'] ?? null) || !filled($payment['cheque_date'] ?? null)) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Self voucher row {$row} mein cheque number aur cheque date required hain.",
                    ]);
                }
            }

            if ($method === 'atm' && !filled($payment['reff_no'] ?? null)) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} mein ATM reference number required hai.",
                ]);
            }

            if ($method === 'cheque' && (int) ($payment['cheque_id'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} mein cheque selection required hai.",
                ]);
            }

            if ($method === 'slip' && (int) ($payment['slip_id'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'payment_details_array' => "Self voucher row {$row} mein slip selection required hai.",
                ]);
            }
        }
    }

    private function normalizeVoucherPaymentMethod(?string $method): string
    {
        return strtolower(str_replace([' ', '_', '.'], '', (string) $method));
    }

    private function selfAccountDisplayLabel(?string $accountTitle): string
    {
        $parts = collect(explode('|', (string) $accountTitle))
            ->map(fn ($part) => trim($part))
            ->filter();

        return (string) ($parts->last() ?: $accountTitle ?: '-');
    }
}
