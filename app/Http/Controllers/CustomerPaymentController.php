<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentClear;
use App\Models\PaymentProgram;
use App\Models\Setup;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerPaymentController extends Controller
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
            $payments = CustomerPayment::whereNotNull('customer_id')
                // ->where('type', '!=', 'DR')
                ->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $payments, 'authLayout' => $authLayout]);
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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        // --- Banks options ---
        $banks_options = Setup::where('type', 'bank_name')->get()->mapWithKeys(function ($bank) {
            return [(int)$bank->id => [
                'text' => $bank->title,
                'data_option' => $bank,
            ]];
        })->toArray();

        $programId = $request->query('program_id');

        // --- Last record ---
        $lastRecord = CustomerPayment::latest('id')
            ->with('customer', 'customer.paymentPrograms.subCategory.bankAccounts.bank')
            ->whereNotNull('customer_id')
            ->first();

        // --- If program_id provided, load specific program and customer ---
        if (!empty($programId)) {
            $program = PaymentProgram::with('customer', 'subCategory.bankAccounts.bank')
                ->withPaymentDetails()
                ->where('balance', '>', 0)
                ->find($programId);

            if ($program && $program->customer) {
                $program->customer['payment_programs'] = $program->toArray();
                $customers_options = [
                    (int)$program->customer->id => [
                        'text' => $program->customer->customer_name . ' | ' . $program->customer->city->title,
                        'data_option' => $program->customer,
                    ]
                ];

                return view("customer-payments.create", compact("customers_options", "banks_options", 'lastRecord'));
            }
        }

        // --- Load all active customers with necessary relations ---
        $customers = Customer::with([
            'orders',
            'payments',
            'paymentPrograms' => fn($q) => $q->where('status', 'Unpaid'),
            'paymentPrograms.subCategory.bankAccounts.bank',
            'city'
        ])->whereHas('user', fn($q) => $q->where('status', 'active'))
        ->select('id', 'customer_name', 'date', 'city_id')
        ->get();

        // --- Prepare customers options and calculate totals ---
        $customers_options = $customers->mapWithKeys(function ($customer) {
            // Total amounts
            $customer['totalAmount'] = $customer->orders->sum(fn($order) => $order->netAmount);
            $customer['totalPayment'] = $customer->payments->sum(fn($payment) => $payment->amount);

            // Fix subCategory for each payment program
            foreach ($customer->paymentPrograms as $program) {
                $subCategory = $program->subCategory;
                if (isset($subCategory->type) && $subCategory->type !== '"App\Models\BankAccount"') {
                    $program->subCategory = $subCategory->bankAccounts ?? null;
                }
            }

            return [(int)$customer->id => [
                'text' => $customer->customer_name . ' | ' . $customer->city->title,
                'data_option' => $customer,
            ]];
        })->toArray();

        return view("customer-payments.create", compact("customers_options", 'banks_options', 'lastRecord'));
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
            "customer_id" => "required|integer|exists:customers,id",
            "date" => "required|date",
            "type" => "required|string",
            "method" => "required|string",
            "amount" => "required|integer",
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
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        $data = $request->all();

        CustomerPayment::create($data);

        if (isset($data['program_id']) && $data['program_id']) {
            $program = PaymentProgram::find($data['program_id']);
            if ($program && $data['method'] == 'program') {
                if ($program['category'] == 'supplier') {
                    $data['supplier_id'] = $program->sub_category_id;
                    SupplierPayment::create($data);
                }
                // else if ($program['category'] == 'customer') {
                //     $data['customer_id'] = $program->sub_category_id;
                //     CustomerPayment::create($data);
                // }
            }
        }

        $currentProgram = PaymentProgram::find($request->program_id);

        if (isset($currentProgram)) {
            if ($currentProgram->balance <= 1000 && $currentProgram->balance >= 0) {
                $currentProgram->status = 'Paid';
                $currentProgram->save();
            } else if ($currentProgram->balance < 0.0) {
                $currentProgram->status = 'Overpaid';
                $currentProgram->save();
            } else {
                $currentProgram->status = 'Unpaid';
                $currentProgram->save();
            }
        }

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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $customerPayment->load('customer.paymentPrograms.subCategory.bankAccounts.bank');

        $banks_options = [];
        $banks = Setup::where('type', 'bank_name')->get();
        foreach ($banks as $bank) {
            if ($bank) {
                $banks_options[(int)$bank->id] = [
                    'text' => $bank->title,
                    'data_option' => $bank,
                ];
            }
        }

        $cheque_nos = CustomerPayment::where('cheque_no', "!==", $customerPayment['cheque_no'])->pluck('cheque_no')->toArray();
        $slip_nos = CustomerPayment::where('slip_no', "!==", $customerPayment['slip_no'])->pluck('slip_no')->toArray();

        return view('customer-payments.edit', compact('customerPayment', 'banks_options'));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerPayment $customerPayment)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $validator = Validator::make($request->all(), [
            "date" => "required|date",
            "type" => "required|string",
            "method" => "required|string",
            "amount" => "required|integer",
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
            return redirect()->back()->withErrors($validator);
        }

        $data = $request->all();

        $customerPayment->update($data);

        if (isset($data['program_id']) && $data['program_id']) {
            SupplierPayment::where([
                'program_id'     => $data['program_id'],
                'method'         => $data['method'],
                'transaction_id' => $data['transaction_id'],
                'bank_account_id'=> $data['bank_account_id'],
            ])->delete();

            $program = PaymentProgram::find($data['program_id']);
            if ($program && $data['method'] == 'program') {
                if ($program['category'] == 'supplier') {
                    $data['supplier_id'] = $program->sub_category_id;
                    SupplierPayment::create($data);
                }
            }
        }

        $currentProgram = PaymentProgram::find($request->program_id);

        if (isset($currentProgram)) {
            if ($currentProgram->balance <= 1000 && $currentProgram->balance >= 0) {
                $currentProgram->status = 'Paid';
                $currentProgram->save();
            } else if ($currentProgram->balance < 0.0) {
                $currentProgram->status = 'Overpaid';
                $currentProgram->save();
            } else {
                $currentProgram->status = 'Unpaid';
                $currentProgram->save();
            }
        }

        return redirect()->route('customer-payments.index')->with('success', 'Payment update successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerPayment $customerPayment)
    {
        //
    }

    public function clear(Request $request, $id) {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $validator = Validator::make($request->all(), [
            'clear_date' => 'required|date',
            'method_select' => 'required|string',
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'amount' => 'required|integer',
            'reff_no' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $data = $request->all();
        $data['method'] = $data['method_select'];
        $data['payment_id'] = $id;

        PaymentClear::create($data);

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
            return redirect()->back()->withErrors($validator);
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
}
