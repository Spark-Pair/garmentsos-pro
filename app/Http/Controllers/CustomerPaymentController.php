<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentClear;
use App\Models\PaymentProgram;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CustomerPaymentController extends Controller
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
            $payments = app(ModuleBranchService::class)->applyScope(CustomerPayment::whereNotNull('customer_id'), 'customer_payments')
                ->where('type', '!=', 'sales_return')
                ->with([
                    'customer.city',
                    'cheque.supplier',
                    'cheque.voucher.supplier.bankAccounts.bank',
                    'cheque.cr.voucher.supplier.bankAccounts.bank',
                    'returnedCheque.cr',
                    'slip.supplier',
                    'slip.voucher.supplier.bankAccounts.bank',
                    'slip.cr.voucher.supplier.bankAccounts.bank',
                    'returnedSlip.cr',
                    'program.subCategory',
                    'bankAccount.bank',
                    'bankAccount.subCategory',
                    'paymentClearRecord.bankAccount.bank',
                    'paymentClearRecord.creator',
                    'dr',
                ])
                ->orderByDesc('id')
                ->applyFilters($request);
                
            $totalAmount = $payments->sum(fn($payment) => (float) ($payment['amount_numeric'] ?? 0));
            $totalPayment = $payments->sum(fn($payment) => (float) ($payment['cleared_amount_numeric'] ?? 0));

            return response()->json([
                'data' => $payments,
                'authLayout' => $authLayout,
                'calculations' => [
                    'total_amount' => $totalAmount,
                    'total_payment' => $totalPayment,
                    'balance' => $totalAmount - $totalPayment
                ]
            ]);
        }

        // Eager load only necessary relations
        // $payments = CustomerPayment::with([
        //     'customer.city',
        //     'cheque.voucher.supplier.bankAccounts.bank',
        //     'slip.voucher.supplier.bankAccounts.bank',
        //     'cheque.cr',
        //     'slip.cr',
        //     'bankAccount.subCategory',
        //     'paymentClearRecord',
        //     'dr',
        // ])
        // ->whereNotNull('program_id')
        // ->whereNotNull('customer_id')
        // ->where('type', '!=', 'DR')
        // ->orderByDesc('id')
        // ->get();


        // $payments = CustomerPayment::with([
        //     'customer.city',
        //     'cheque.voucher.supplier.bankAccounts.bank',
        //     'slip.voucher.supplier.bankAccounts.bank',
        //     'cheque.cr',
        //     'slip.cr',
        //     'bankAccount.subCategory',
        //     'paymentClearRecord',
        //     'dr'
        // ])
        // ->whereNotNull('customer_id')
        // ->where('type', '!=', 'DR')
        // ->orderByDesc('id')
        // ->applyFilters($request)->get()->mapWithKeys(function ($item) {
        //     return [
        //         $item->id => [
        //             'id' => $item->id,
        //             'name' => $item->customer->customer_name . ' | ' . $item->customer->city->title,
        //             'details' => [
        //                 'Type' => $item->type,
        //                 'Method' => $item->method,
        //                 'Date' => $item->slip_date ? $item->slip_date->format('d-M-Y, D') : ($item->cheque_date ? $item->cheque_date->format('d-M-Y, D') : $item->date->format('d-M-Y, D')),
        //                 'Amount' => $item->amount,
        //             ],
        //             'data' => $item,
        //             'date' => $item->slip_date ? $item->slip_date->format('d-M-Y, D') : ($item->cheque_date ? $item->cheque_date->format('d-M-Y, D') : $item->date->format('d-M-Y, D')),
        //             'voucher_no' => $item->voucher_no,
        //             'supplier_name' => $item->supplier_name,
        //             'reff_no' => $item->reff_no,
        //             'beneficiary' => $item->beneficiary,
        //             'clear_date' => $item->clear_date ? $item->clear_date->format('d-M-Y, D') : $item->paymentClearRecord->last()?->clear_date,
        //             'cleared_amount' => $item->cleared_amount,
        //             'oncontextmenu' => "generateContextMenu(event)",
        //             'onclick' => "generateModal(this)",
        //         ]
        //     ];
        // })->values();

        // return $payments[1];

        // $payments = CustomerPayment::
        // whereNotNull('customer_id')
        // ->whereHas('cheque')
        // ->orderByDesc('id')
        // ->get();

        // // return $payments[0]->getvoucherNo();

        // // Preload all reference numbers by type to reduce memory
        // $allChequeRefs = CustomerPayment::whereNotNull('cheque_no')->pluck('cheque_no');
        // $allSlipRefs   = CustomerPayment::whereNotNull('slip_no')->pluck('slip_no');
        // $allProgramRefs= CustomerPayment::whereNotNull('transaction_id')->pluck('transaction_id');
        // $allReffRefs   = CustomerPayment::whereNotNull('reff_no')->pluck('reff_no');

        // // Preload SupplierPayments for all program payments in batch
        // $programPaymentIds = $payments->filter(fn($p) => $p->method === 'program' && $p->program_id)->pluck('program_id')->unique();
        // $programVouchers = SupplierPayment::with('voucher')
        //     ->whereIn('program_id', $programPaymentIds)
        //     ->get()
        //     ->keyBy(fn($sp) => $sp->program_id . '_' . ($sp->transaction_id ?? 'null') . '_' . ($sp->supplier_id ?? 'null'));

        // foreach ($payments as $payment) {

        //     /* ================= Issued / Return / Not Issued ================= */
        //     if ((($payment->cheque || $payment->slip) || in_array($payment->method, ['cheque','slip']) && $payment->bank_account_id) && !$payment->is_return) {
        //         $payment->issued = 'Issued';
        //     } elseif ($payment->is_return && $payment->d_r_id === null) {
        //         $payment->issued = 'Return';
        //     } else {
        //         $payment->issued = 'Not Issued';
        //     }

        //     if ($payment->d_r_id !== null) {
        //         $payment->issued = 'DR';
        //     }

        //     /* ================= Clear Amount Logic ================= */
        //     if ($payment->clear_date && $payment->clear_date !== 'Pending') {
        //         $payment->clear_amount = $payment->amount;
        //     } else {
        //         $payment->clear_amount = $payment->paymentClearRecord->sum('amount');
        //         if ($payment->clear_amount >= $payment->amount) {
        //             $payment->clear_date = $payment->paymentClearRecord->last()?->clear_date;
        //         }
        //     }

        //     if (!$payment->clear_date && in_array($payment->type, ['cheque','slip'])) {
        //         $payment->clear_date = 'Pending';
        //     }

        //     /* ================= City Title ================= */
        //     if ($payment->customer?->city) {
        //         $payment->customer->city->title .= ' | ' . $payment->customer->city->short_title;
        //     }

        //     /* ================= Remarks Fallback ================= */
        //     $payment->remarks ??= 'No Remarks';

        //     /* ================= Program Voucher ================= */
        //     if ($payment->method === 'program' && $payment->program_id) {
        //         $key = $payment->program_id . '_' . ($payment->transaction_id ?? 'null') . '_' . ($payment->bankAccount->sub_category_id ?? 'null');
        //         $payment->voucher = $programVouchers->get($key)?->voucher;
        //     }

        //     /* ================= Reference Numbers ================= */
        //     $raw = match ($payment->method) {
        //         'cheque'  => $payment->cheque_no,
        //         'slip'    => $payment->slip_no,
        //         'program' => $payment->transaction_id,
        //         default   => $payment->reff_no,
        //     };

        //     $baseRef = trim(explode('|', $raw)[0]);
        //     $payment->has_pipe = str_contains($raw, '|');
        //     $payment->existing_reff_nos = [];
        //     $payment->max_reff_suffix = 0;

        //     if ($baseRef) {
        //         $refs = match ($payment->method) {
        //             'cheque'  => $allChequeRefs,
        //             'slip'    => $allSlipRefs,
        //             'program' => $allProgramRefs,
        //             default   => $allReffRefs,
        //         };

        //         $refs = $refs->filter(fn($v) => $v && str_starts_with($v, $baseRef))->values()->toArray();
        //         $payment->existing_reff_nos = $refs;

        //         foreach ($refs as $ref) {
        //             if (str_contains($ref, '|')) {
        //                 [, $n] = array_map('trim', explode('|',$ref));
        //                 if (is_numeric($n)) {
        //                     $payment->max_reff_suffix = max($payment->max_reff_suffix, (int)$n);
        //                 }
        //             }
        //         }
        //     }
        // }

        return view("customer-payments.index", compact( "authLayout"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // --- Banks options ---
        $branches = app(ModuleBranchService::class);

        $banks_options = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'customer_payments')
            ->select('id', 'title')
            ->get()
            ->mapWithKeys(function ($bank) {
            return [(int)$bank->id => [
                'text' => $bank->title,
                'data_option' => $this->formatSetupPayload($bank),
            ]];
        })->toArray();

        $programId = $request->query('program_id');

        // --- Last record ---
        $lastRecord = $branches->applyScope(CustomerPayment::latest('id'), 'customer_payments')
            ->with('customer:id,customer_name')
            ->whereNotNull('customer_id')
            ->where('type', '!=', 'sales_return')
            ->first();
        $lastRecordPayload = $lastRecord ? $this->formatLastPaymentPayload($lastRecord) : null;

        $programPayload = null;
        $programCustomerId = null;

        // --- If program_id provided, load specific program and customer ---
        if (!empty($programId)) {
            $program = $branches->applyScope(PaymentProgram::with([
                'customer:id,customer_name,city_id,date',
                'customer.city:id,title',
                'subCategory',
                'subCategory.bankAccounts.bank:id,short_title',
            ]), 'payment_programs')
                ->withSum('customerPayments as paid_amount', 'amount')
                ->find($programId);

            if ($program && $program->customer) {
                $programBranchId = (int) ($program->branch_id ?? 0);
                if ($programBranchId && $branches->isModuleBranchEnabled('customer_payments', $programBranchId)) {
                    if (!$branches->canSwitch($programBranchId, 'customer_payments', $request->user())) {
                        return redirect()->route('customer-payments.create')
                            ->with('error', 'You cannot add a customer payment for this program branch.');
                    }

                    $branches->setPreference('customer_payments', $programBranchId, $request->user());
                }

                $programPayload = $this->formatProgramPayload($program);
                if (($programPayload['balance'] ?? 0) <= 0) {
                    return redirect()->route('customer-payments.create')
                        ->with('error', 'Selected payment program is already cleared.');
                }

                $customerPayload = $this->customerOptionPayloads(collect([$program->customer]), 'customer_payments')[(int) $program->customer->id];
                $customerPayload['payment_programs'] = $programPayload;

                $customers_options = [
                    (int)$program->customer->id => [
                        'text' => $program->customer->customer_name . ' | ' . $program->customer->city->title,
                        'data_option' => $customerPayload,
                    ]
                ];

                $programCustomerId = $program->customer->id;

                return view("customer-payments.create", compact("customers_options", "banks_options", 'lastRecord', 'lastRecordPayload', 'programPayload', 'programCustomerId'));
            }
        }

        // --- Load all active customers with lightweight aggregated payload ---
        $customers = $branches->applyRelatedScope(Customer::with([
            'city:id,title',
            'paymentPrograms' => function ($query) use ($branches) {
                $branches->applyRelatedScope($query->getQuery(), 'payment_programs', 'customer_payments');

                $query->select('id', 'program_no', 'order_no', 'date', 'customer_id', 'category', 'sub_category_id', 'sub_category_type', 'amount', 'remarks', 'status', 'branch_id')
                    ->where('status', 'Unpaid')
                    ->withSum('customerPayments as paid_amount', 'amount')
                    ->with([
                        'subCategory',
                        'subCategory.bankAccounts.bank:id,short_title',
                    ]);
            },
        ]), 'customers', 'customer_payments')
            ->withSum('invoices as total_invoice_amount', 'netAmount')
            ->withSum(['payments as total_paid_amount' => fn($q) => $q->where('type', '!=', 'DR')], 'amount')
            ->withSum(['statementAdjustments as adjustment_plus_amount' => fn($q) => $q->where('direction', 'plus')], 'amount')
            ->withSum(['statementAdjustments as adjustment_minus_amount' => fn($q) => $q->where('direction', 'minus')], 'amount')
            ->whereHas('user', fn($q) => $q->where('status', 'active'))
            ->select('id', 'customer_name', 'date', 'city_id')
            ->get();

        $customerPayloads = $this->customerOptionPayloads($customers, 'customer_payments');
        $customers_options = $customers->mapWithKeys(function ($customer) use ($customerPayloads) {
            $programs = $customer->paymentPrograms
                ->map(fn($program) => $this->formatProgramPayload($program))
                ->filter(fn($program) => ($program['balance'] ?? 0) > 0)
                ->values()
                ->all();

            $customerPayload = $customerPayloads[(int) $customer->id];
            $customerPayload['payment_programs'] = $programs;

            return [(int)$customer->id => [
                'text' => $customer->customer_name . ' | ' . $customer->city->title,
                'data_option' => $customerPayload,
            ]];
        })->toArray();

        return view("customer-payments.create", compact("customers_options", 'banks_options', 'lastRecord', 'lastRecordPayload', 'programPayload', 'programCustomerId'));
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
            "customer_id" => "required|integer|exists:customers,id",
            "date" => "required|date",
            "type" => "required|string",
            "method" => "required|string",
            "amount" => "required|integer|min:1",
            "bank_id" => "nullable|integer|exists:setups,id",
            "cheque_date" => "nullable|date",
            "slip_date" => "nullable|date",
            "clear_date" => "nullable|date",
            "bank_account_id" => "nullable|integer|exists:bank_accounts,id",

            // ---------------------------------------------------
            // CHEQUE UNIQUE RULE (customer_id + bank_id + cheque_date + cheque_no)
            // ---------------------------------------------------
            "cheque_no" => [
                "nullable",
                "string",
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $value !== "0") {
                        $exists = CustomerPayment::where('customer_id', (int)$request->customer_id)
                            ->where('bank_id', (int)$request->bank_id)
                            ->whereDate('cheque_date', $request->cheque_date) // use whereDate for proper date comparison
                            ->where('cheque_no', $value)
                            ->exists();

                        if ($exists) {
                            $fail('The cheque number has already been taken for this customer, bank and date.');
                        }
                    }
                },
            ],

            // ---------------------------------------------------
            // SLIP UNIQUE RULE (customer_id + slip_date + slip_no) — NO bank check
            // ---------------------------------------------------
            "slip_no" => [
                "nullable",
                "string",
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $value !== "0") {
                        $exists = CustomerPayment::where('customer_id', (int)$request->customer_id)
                            ->whereDate('slip_date', $request->slip_date) // proper date comparison
                            ->where('slip_no', $value)
                            ->exists();

                        if ($exists) {
                            $fail('The slip number has already been taken for this customer and slip date.');
                        }
                    }
                },
            ],

            // ---------------------------------------------------
            // TRANSACTION ID UNIQUE (skip when = "0")
            // ---------------------------------------------------
            "transaction_id" => [
                "nullable",
                "string",
                Rule::unique("customer_payments", "transaction_id")
                    ->whereNot("transaction_id", "0"),
            ],

            "program_id" => "nullable|exists:payment_programs,id",
            "remarks" => "nullable|string",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }

        $payload = $this->buildCustomerPaymentPayload($request);

        try {
            $program = $this->normalizeProgramPaymentPayload($payload);
        } catch (ValidationException $exception) {
            return redirect()->back()
                ->withErrors($exception->errors())
                ->withInput()
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        DB::transaction(function () use ($payload, $program) {
            CustomerPayment::create($payload);

            if ($program && $payload['method'] === 'program' && $program->category === 'supplier') {
                SupplierPayment::create(array_merge($payload, [
                    'supplier_id' => $program->sub_category_id,
                    'branch_id' => $payload['branch_id'],
                ]));
            }

            if ($program) {
                $this->syncProgramStatus($program->id);
            }
        });

        return redirect()->back()->with('success', 'Payment Added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerPayment $customerPayment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomerPayment $customerPayment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $customerPayment->load('customer');

        $banks_options = [];
        $banks = Setup::where('type', 'bank_name')->get();
        foreach ($banks as $bank) {
            if ($bank) {
                $banks_options[(int)$bank->id] = [
                    'text' => $bank->title,
                    'data_option' => $this->formatSetupPayload($bank),
                ];
            }
        }

        $programs = $customerPayment->customer->paymentPrograms()
            ->select('id', 'program_no', 'order_no', 'date', 'customer_id', 'category', 'sub_category_id', 'sub_category_type', 'amount', 'remarks', 'status')
            ->withSum('customerPayments as paid_amount', 'amount')
            ->with(['subCategory' => fn($q) => $q->with('bankAccounts.bank')])
            ->get();

        $customerPaymentPayload = [
            'id' => $customerPayment->id,
            'customer_id' => $customerPayment->customer_id,
            'date' => $customerPayment->date?->format('Y-m-d'),
            'type' => $customerPayment->type,
            'method' => $customerPayment->method,
            'amount' => $customerPayment->amount,
            'cheque_no' => $customerPayment->cheque_no,
            'slip_no' => $customerPayment->slip_no,
            'transaction_id' => $customerPayment->transaction_id,
            'cheque_date' => $customerPayment->cheque_date?->format('Y-m-d'),
            'slip_date' => $customerPayment->slip_date?->format('Y-m-d'),
            'clear_date' => $customerPayment->clear_date?->format('Y-m-d'),
            'bank_id' => $customerPayment->bank_id,
            'bank_account_id' => $customerPayment->bank_account_id,
            'program_id' => $customerPayment->program_id,
            'remarks' => $customerPayment->remarks,
            'customer' => [
                'id' => $customerPayment->customer->id,
                'customer_name' => $customerPayment->customer->customer_name,
                'date' => $customerPayment->customer->date?->format('Y-m-d'),
                'payment_programs' => $programs->map(fn($program) => $this->formatProgramPayload($program))->values()->all(),
            ],
        ];

        return view('customer-payments.edit', compact('customerPayment', 'banks_options', 'customerPaymentPayload'));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerPayment $customerPayment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            "customer_id" => "required|integer|exists:customers,id",
            "date" => "required|date",
            "type" => "required|string",
            "method" => "required|string",
            "amount" => "required|integer|min:1",
            "bank_id" => "nullable|integer|exists:setups,id",
            "cheque_date" => "nullable|date",
            "slip_date" => "nullable|date",

            // -----------------------------------------
            // CHEQUE UNIQUE RULE (IGNORE CURRENT ROW)
            // -----------------------------------------
            "cheque_no" => [
                "nullable",
                "string",
                function ($attribute, $value, $fail) use ($request, $customerPayment) {
                    if ($value && $value !== "0") {
                        $exists = CustomerPayment::where('customer_id', (int)$request->customer_id)
                            ->where('bank_id', (int)$request->bank_id)
                            ->whereDate('cheque_date', $request->cheque_date)
                            ->where('cheque_no', $value)
                            ->where('id', '!=', $customerPayment->id) // IGNORE CURRENT RECORD
                            ->exists();

                        if ($exists) {
                            $fail('This cheque number already exists for this customer, bank and date.');
                        }
                    }
                },
            ],

            // -----------------------------------------
            // SLIP UNIQUE RULE (IGNORE CURRENT ROW)
            // -----------------------------------------
            "slip_no" => [
                "nullable",
                "string",
                function ($attribute, $value, $fail) use ($request, $customerPayment) {
                    if ($value && $value !== "0") {
                        $exists = CustomerPayment::where('customer_id', (int)$request->customer_id)
                            ->whereDate('slip_date', $request->slip_date)
                            ->where('slip_no', $value)
                            ->where('id', '!=', $customerPayment->id) // IGNORE CURRENT RECORD
                            ->exists();

                        if ($exists) {
                            $fail('This slip number already exists for this customer and slip date.');
                        }
                    }
                },
            ],

            // -----------------------------------------
            // TRANSACTION ID UNIQUE (IGNORE CURRENT ROW)
            // -----------------------------------------
            "transaction_id" => [
                "nullable",
                "string",
                Rule::unique("customer_payments", "transaction_id")
                    ->ignore($customerPayment->id)
                    ->whereNot("transaction_id", "0"),
            ],

            "clear_date" => "nullable|date",
            "bank_account_id" => "nullable|integer|exists:bank_accounts,id",
            "program_id" => "nullable|exists:payment_programs,id",
            "remarks" => "nullable|string",
        ]);


        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $payload = $this->buildCustomerPaymentPayload($request);
        $oldProgramId = $customerPayment->program_id;

        try {
            $program = $this->normalizeProgramPaymentPayload($payload);
        } catch (ValidationException $exception) {
            return redirect()->back()
                ->withErrors($exception->errors())
                ->withInput()
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        DB::transaction(function () use ($payload, $customerPayment, $program, $oldProgramId) {
            $payload['is_return'] = $customerPayment->is_return; // Preserve return status
            $customerPayment->update($payload);

            if (!empty($payload['program_id'])) {
                SupplierPayment::where([
                    'program_id' => $payload['program_id'],
                    'method' => $payload['method'],
                    'transaction_id' => $payload['transaction_id'],
                    'bank_account_id' => $payload['bank_account_id'],
                ])->delete();

                if ($program && $payload['method'] === 'program' && $program['category'] == 'supplier') {
                    SupplierPayment::create(array_merge($payload, [
                        'supplier_id' => $program->sub_category_id,
                        'branch_id' => $payload['branch_id'],
                    ]));
                }

                $this->syncProgramStatus((int) $payload['program_id']);
            }

            if (!empty($oldProgramId) && (int) $oldProgramId !== (int) ($payload['program_id'] ?? 0)) {
                $this->syncProgramStatus((int) $oldProgramId);
            }
        });

        return redirect()->route('customer-payments.index')->with('success', 'Payment update successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerPayment $customerPayment)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($customerPayment, 'customer_payments');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $dependencies = [];

        // Already used inside a Voucher / Self-Account payment (cheque_id / slip_id linked)
        $linkedSupplierPayment = SupplierPayment::where('cheque_id', $customerPayment->id)
            ->orWhere('slip_id', $customerPayment->id)
            ->first();

        if ($linkedSupplierPayment) {
            if ($linkedSupplierPayment->voucher_id) {
                $dependencies['voucher'] = 1;
            } elseif ($linkedSupplierPayment->self_account_id) {
                $dependencies['self account payment'] = 1;
            } else {
                $dependencies['supplier payment'] = 1;
            }
        }

        // Program payment already issued (matched to a voucher/CR on supplier side)
        if ($customerPayment->method === 'program' && $customerPayment->hasProgramVoucher()) {
            $dependencies['issued program voucher'] = 1;
        }

        // Has (partial or full) clearance history
        if ($customerPayment->paymentClearRecord()->exists()) {
            $dependencies['clearance record'] = 1;
        }

        // Linked to / part of a Debit Return
        if ($customerPayment->d_r_id) {
            $dependencies['debit return'] = 1;
        } elseif ($customerPayment->is_return) {
            $dependencies['return record'] = 1;
        }

        if (!empty($dependencies)) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Customer payment', $dependencies));
        }

        $programId = $customerPayment->program_id;

        DB::transaction(function () use ($customerPayment, $programId) {
            // Remove the un-issued SupplierPayment auto-created for this program payment (if any)
            SupplierPayment::where([
                'program_id' => $programId,
                'method' => $customerPayment->method,
                'transaction_id' => $customerPayment->transaction_id,
                'bank_account_id' => $customerPayment->bank_account_id,
            ])
                ->whereNull('voucher_id')
                ->whereNull('c_r_id')
                ->delete();

            $customerPayment->delete();

            if ($programId) {
                $this->syncProgramStatus((int) $programId);
            }
        });

        return redirect()->route('customer-payments.index')->with('success', 'Payment deleted successfully.');
    }

    public function clear(Request $request, $id) {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'clear_date' => 'required|date',
            'method_select' => 'required|string',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'amount' => 'required|integer|min:1',
            'reff_no' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $validator->sometimes('bank_account_id', 'required|integer|exists:bank_accounts,id', function ($input) {
            return isset($input->method_select) && $input->method_select !== 'cash';
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $payment = CustomerPayment::with('paymentClearRecord')->find($id);
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment record not found.');
        }

        if (empty($payment->cheque_no) && empty($payment->slip_no)) {
            return redirect()->back()->withInput()->with('error', 'Only cheque or slip payments can be cleared.');
        }

        $alreadyCleared = (float) $payment->paymentClearRecord->sum('amount');
        $remaining = (float) $payment->amount - $alreadyCleared;

        if ($remaining <= 0) {
            return redirect()->back()->with('error', 'Payment already fully cleared.');
        }

        if ((float) $request->amount > $remaining) {
            return redirect()->back()->withInput()->with('error', 'Clear amount cannot be greater than the remaining outstanding amount.');
        }

        $reffNo = $request->reff_no ?: '-';

        PaymentClear::create([
            'payment_id' => $id,
            'clear_date' => $request->clear_date,
            'method' => $request->method_select,
            'bank_account_id' => $request->bank_account_id,
            'amount' => $request->amount,
            'reff_no' => $reffNo,
            'remarks' => $request->remarks,
        ]);

        $totalCleared = (float) $payment->paymentClearRecord()->sum('amount');
        if ($totalCleared >= (float) $payment->amount) {
            $payment->clear_date = $request->clear_date;
            $payment->save();
        }

        return redirect()->back()->with('success', 'Payment partial cleared successfully.');
    }

    // public function split(Request $request, CustomerPayment $payment)
    // {
    //     if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
    //         return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'split_amount' => 'required|integer|min:1|max:' . ($payment->amount - 1),
    //     ]);

    //     if ($validator->fails()) {
    //         return redirect()->back()->withErrors($validator);
    //     }

    //     // Determine reference field
    //     $reffField = match ($payment->method) {
    //         'cheque'   => 'cheque_no',
    //         'slip'     => 'slip_no',
    //         'program'  => 'transaction_id',
    //         default    => 'reff_no',
    //     };

    //     // Get base (before | n)
    //     $currentReff = $payment->$reffField;
    //     $parts = explode('|', $currentReff);
    //     $baseReff = trim($parts[0]);

    //     // Find max suffix already used for this base
    //     $maxSuffix = CustomerPayment::where($reffField, 'like', $baseReff.' | %')
    //         ->pluck($reffField)
    //         ->map(function ($r) use ($baseReff) {
    //             $pieces = explode('|', $r);
    //             return isset($pieces[1]) ? (int) trim($pieces[1]) : 0;
    //         })
    //         ->max();

    //     // If no suffix found, start from 1
    //     if (!$maxSuffix) {
    //         $maxSuffix = 1;
    //         // Update original payment reff_no → base | 1
    //         $payment->$reffField = $baseReff . ' | ' . $maxSuffix;
    //     }

    //     // Step 1: Reduce amount in original payment
    //     $payment->amount = $payment->amount - $request->split_amount;
    //     $payment->save();

    //     // Step 2: Create duplicate with next suffix
    //     $newPayment = $payment->replicate();
    //     $newPayment->amount = $request->split_amount;
    //     $newPayment->$reffField = $baseReff . ' | ' . ($maxSuffix + 1);
    //     $newPayment->save();

    //     return redirect()->back()->with('success', 'Payment split successfully.');
    // }

    public function split(Request $request, CustomerPayment $payment)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))
                ->with('error', 'You do not have permission to access this page.');
        }

        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'split_amount' => 'required|integer|min:1|max:' . ($payment->amount - 1),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        /**
         * 🔹 Reference field (Customer + Supplier for program/default)
         */
        $reffField = match ($payment->method) {
            'cheque'   => 'cheque_no',
            'slip'     => 'slip_no',
            'program'  => 'transaction_id',
            default    => 'reff_no',
        };

        /**
         * 🔹 Base reference
         */
        $baseReff = trim(explode('|', $payment->$reffField)[0]);

        /**
         * 🔹 Max suffix (Customer side decides truth)
         */
        $maxSuffix = CustomerPayment::where($reffField, 'like', $baseReff . ' | %')
            ->pluck($reffField)
            ->map(fn ($r) => (int) trim(explode('|', $r)[1] ?? 0))
            ->max() ?? 0;

        /**
         * 🔹 Find linked SupplierPayment
         */
        $supplierPayment = match ($payment->method) {

            // 🔗 ID based (cheque / slip)
            'cheque' => SupplierPayment::where('cheque_id', $payment->id)->first(),
            'slip'   => SupplierPayment::where('slip_id', $payment->id)->first(),

            // 🔁 Same process as customer (program / default)
            default  => SupplierPayment::where($reffField, $payment->$reffField)
                            ->where('amount', $payment->amount)
                            ->whereDate('date', $payment->date)
                            ->first(),
        };

        /**
         * 🔒 TRANSACTION
         */
        DB::transaction(function () use (
            $payment,
            $supplierPayment,
            $request,
            $reffField,
            $baseReff,
            &$maxSuffix
        ) {

            /**
             * 🧾 First split → update OLD reff on BOTH sides
             */
            if ($maxSuffix === 0) {

                $payment->$reffField = $baseReff . ' | 1';
                $payment->save();

                if ($supplierPayment && !in_array($payment->method, ['cheque', 'slip'])) {
                    $supplierPayment->$reffField = $baseReff . ' | 1';
                    $supplierPayment->save();
                }

                $maxSuffix = 1;
            }

            /**
             * 1️⃣ Reduce OLD amounts
             */
            $payment->amount -= $request->split_amount;
            $payment->save();

            if ($supplierPayment) {
                $supplierPayment->amount -= $request->split_amount;
                $supplierPayment->save();
            }

            /**
             * 2️⃣ Create NEW CustomerPayment
             */
            $newCustomer = $payment->replicate();
            $newCustomer->amount = $request->split_amount;
            $newCustomer->$reffField = $baseReff . ' | ' . ($maxSuffix + 1);
            $newCustomer->save();

            /**
             * 3️⃣ Create NEW SupplierPayment
             */
            if ($supplierPayment) {

                $newSupplier = $supplierPayment->replicate();
                $newSupplier->amount = $request->split_amount;

                if ($payment->method === 'cheque') {
                    $newSupplier->cheque_id = $newCustomer->id;
                } elseif ($payment->method === 'slip') {
                    $newSupplier->slip_id = $newCustomer->id;
                } else {
                    $newSupplier->$reffField = $baseReff . ' | ' . ($maxSuffix + 1);
                }

                $newSupplier->save();
            }
        });

        return redirect()->back()->with('success', 'Payment split successfully.');
    }

    private function formatProgramPayload(PaymentProgram $program): array
    {
        $paidAmount = (float)($program->paid_amount ?? 0);
        $balance = (float)$program->amount - $paidAmount;
        $subCategory = $program->subCategory;
        $bankAccounts = collect();

        if ($subCategory instanceof Supplier || $subCategory instanceof Customer) {
            $bankAccounts = $subCategory->bankAccounts ?? collect();
        } elseif ($subCategory instanceof \App\Models\BankAccount) {
            $bankAccounts = collect([$subCategory]);
        }

        $bankAccountsPayload = $bankAccounts
            ->map(fn($account) => [
                'id' => $account->id,
                'account_title' => $account->account_title,
                'bank' => [
                    'short_title' => $account->bank?->short_title ?? '-',
                ],
            ])
            ->values()
            ->all();

        return [
            'id' => $program->id,
            'program_no' => $program->program_no,
            'order_no' => $program->order_no,
            'date' => $program->date?->format('Y-m-d'),
            'category' => $program->category,
            'amount' => (float)$program->amount,
            'payment' => $paidAmount,
            'balance' => $balance,
            'remarks' => $program->remarks,
            'status' => $program->status,
            'has_sub_category' => filled($program->sub_category_id),
            'program_method_available' => filled($program->sub_category_id) && $program->category !== 'waiting',
            'sub_category' => [
                'id' => $subCategory?->id,
                'supplier_name' => $subCategory?->supplier_name ?? null,
                'customer_name' => $subCategory?->customer_name ?? null,
                'account_title' => $subCategory?->account_title ?? null,
                'bank_accounts' => $bankAccountsPayload,
            ],
        ];
    }

    private function normalizeProgramPaymentPayload(array &$payload): ?PaymentProgram
    {
        $method = (string) ($payload['method'] ?? '');
        $programId = $payload['program_id'] ?? null;

        if (blank($programId)) {
            if ($method === 'program') {
                throw ValidationException::withMessages([
                    'program_id' => 'Please select a categorized payment program for program payments.',
                ]);
            }

            return null;
        }

        $program = PaymentProgram::select('id', 'customer_id', 'amount', 'category', 'sub_category_id', 'sub_category_type')
            ->find($programId);

        if (!$program) {
            throw ValidationException::withMessages([
                'program_id' => 'Selected payment program was not found.',
            ]);
        }

        if ((int) $program->customer_id !== (int) $payload['customer_id']) {
            throw ValidationException::withMessages([
                'program_id' => 'Selected payment program does not belong to the selected customer.',
            ]);
        }

        if (blank($program->sub_category_id)) {
            if ($method === 'program') {
                throw ValidationException::withMessages([
                    'method' => 'Program method is available only after the payment program category is selected.',
                ]);
            }

            return $program;
        }

        $payload['method'] = 'program';

        return $program;
    }

    private function formatSetupPayload(Setup $setup): array
    {
        return [
            'id' => $setup->id,
            'title' => $setup->title,
            'short_title' => $setup->short_title ?? null,
            'type' => $setup->type,
        ];
    }

    private function formatLastPaymentPayload(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'customer_name' => $payment->customer->customer_name,
            ] : null,
            'date' => $payment->date?->format('Y-m-d'),
            'type' => $payment->type,
            'method' => $payment->method,
            'amount' => $payment->amount,
            'bank_id' => $payment->bank_id,
            'program_id' => $payment->program_id,
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'reff_no' => $payment->reff_no,
            'transaction_id' => $payment->transaction_id,
            'cheque_date' => $payment->cheque_date?->format('Y-m-d'),
            'slip_date' => $payment->slip_date?->format('Y-m-d'),
            'clear_date' => $payment->clear_date?->format('Y-m-d'),
            'remarks' => $payment->remarks,
        ];
    }

    private function buildCustomerPaymentPayload(Request $request, ?CustomerPayment $existingPayment = null): array
    {
        $isReturn = $request->has('is_return')
            ? (bool) $request->is_return
            : (bool) ($existingPayment?->is_return ?? false);

        return [
            'customer_id' => $request->customer_id,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('customer_payments'),
            'date' => $request->date,
            'type' => $request->type,
            'method' => $request->method,
            'amount' => $request->amount,
            'bank_id' => $request->bank_id,
            'cheque_no' => $request->cheque_no,
            'slip_no' => $request->slip_no,
            'reff_no' => $request->reff_no,
            'transaction_id' => $request->transaction_id,
            'cheque_date' => $request->cheque_date,
            'slip_date' => $request->slip_date,
            'clear_date' => $request->clear_date,
            'bank_account_id' => $request->bank_account_id,
            'program_id' => $request->program_id,
            'is_return' => $isReturn,
            'remarks' => $request->remarks,
        ];
    }

    private function programRemainingBalance(int $programId, ?int $excludePaymentId = null): float
    {
        $program = PaymentProgram::select('id', 'amount')->find($programId);
        if (!$program) {
            return 0;
        }

        $paidAmountQuery = CustomerPayment::where('program_id', $programId);
        if ($excludePaymentId) {
            $paidAmountQuery->where('id', '!=', $excludePaymentId);
        }

        $paidAmount = (float) $paidAmountQuery->sum('amount');

        return max(0.0, (float) $program->amount - $paidAmount);
    }

    private function syncProgramStatus(int $programId): void
    {
        $program = PaymentProgram::select('id', 'amount')->find($programId);
        if (!$program) {
            return;
        }

        $paidAmount = (float) CustomerPayment::where('program_id', $programId)->sum('amount');
        $balance = (float) $program->amount - $paidAmount;

        $status = 'Unpaid';
        if ($balance <= 0) {
            $status = $balance < 0 ? 'Overpaid' : 'Paid';
        }

        PaymentProgram::where('id', $programId)->update(['status' => $status]);
    }
}
