<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\PaymentProgram;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            if ($request->get('status') === '__all__') {
                $request->merge(['status' => '']);
            }

            $branches = app(ModuleBranchService::class);

            $paymentProgramsQuery = PaymentProgram::with([
                'customer.city',
                'subCategory',
            ])
                ->withPaymentDetails()
                ->orderByDesc('id');

            $payment_programs = $branches
                ->applyScope($paymentProgramsQuery, 'payment_programs')
                ->applyFilters($request);

            $totalAmount = (float) $payment_programs->sum(
                fn ($program) => (float) ($program['amount'] ?? 0)
            );

            $totalPayment = (float) $payment_programs->sum(
                fn ($program) => (float) ($program['payment'] ?? 0)
            );

            $totalBalance = (float) $payment_programs->sum(
                fn ($program) => (float) ($program['balance'] ?? 0)
            );

            $totalOrderBalance = (float) $payment_programs->sum(
                fn ($program) => (float) ($program['order_balance'] ?? 0)
            );

            return response()->json([
                'data' => $payment_programs,
                'authLayout' => $authLayout,
                'calculations' => [
                    'total_amount' => $totalAmount,
                    'total_payment' => $totalPayment,
                    'balance' => $totalBalance,
                    'order_balance' => $totalOrderBalance,
                ],
            ]);
        }

        // // Fetch and sort orders by date and created_at
        // $orders = Order::with(['customer.city', 'paymentPrograms.subCategory'])
        //     ->whereHas('customer', function ($q) {
        //         $q->where('category', 'cash');
        //     })
        //     ->orderBy('date', 'asc')
        //     ->orderBy('created_at', 'asc')
        //     ->get();

        // $ordersArray = [];

        // foreach($orders as $order) {
        //     $order['paymentPrograms']['customer'] = $order['customer'];
        //     $ordersArray[] = $order['paymentPrograms'];
        // }

        // // Fetch and sort payment programs by date and created_at
        // $paymentPrograms = PaymentProgram::with('customer.city', 'subCategory')
        //     ->where('order_no', null)
        //     ->orderBy('date', 'asc')
        //     ->orderBy('created_at', 'asc')
        //     ->withPaymentDetails()
        //     ->get();

        // $paymentProgramsArray = $paymentPrograms->toArray();

        // // Combine both arrays manually
        // $finalData = array_merge($ordersArray, $paymentProgramsArray);

        // usort($finalData, function ($a, $b) {
        //     if ($a['date'] == $b['date']) {
        //         return strtotime($b['created_at']) - strtotime($a['created_at']); // time DESC
        //     }
        //     return strtotime($b['date']) - strtotime($a['date']); // date DESC
        // });

        return view("payment-programs.index", compact('authLayout'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $start = microtime(true); // ⏱️ Start timing

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $customers = $branches->applyRelatedScope(Customer::with('city:id,title'), 'customers', 'payment_programs')
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->select('id', 'customer_name', 'date', 'city_id')
            ->get();

        $customers_options = [];

        foreach ($customers as $customer) {
            $customerPayload = $this->customerOptionPayload($customer);

            $customers_options[(int)$customer->id] = [
                'text' => $customer->customer_name . ' | ' . ($customer->city->title ?? 'N/A') . ' | Balance: ' . $customerPayload['balance_formatted'],
                'data_option' => $customerPayload,
            ];
        }

        $loadTime = round(microtime(true) - $start, 3); // ⏱️ End timing

        if (app()->isLocal()) {
            Log::info("PaymentProgram create() load time: {$loadTime} seconds");
        }

        return view('payment-programs.create', compact('customers_options', 'loadTime'));
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
            'date'=> 'required|date',
            'customer_id'=> 'required|integer|exists:customers,id',
            'category'=> 'required|in:supplier,self_account,customer,waiting',
            'sub_category'=> 'nullable|integer',
            'amount'=> 'required|numeric|min:1',
            'remarks'=> 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->category === 'waiting') {
                return;
            }

            if (blank($request->sub_category)) {
                $validator->errors()->add('sub_category', 'Please select a valid category account.');
                return;
            }

            if (! $this->resolveSubCategoryModel((string) $request->category, (int) $request->sub_category)) {
                $validator->errors()->add('sub_category', 'The selected category account is not available.');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $branches = app(ModuleBranchService::class);
            $subCategoryModel = $this->resolveSubCategoryModel($request->category, $request->sub_category);

            $query = $branches->applyScope(
                PaymentProgram::query()->lockForUpdate(),
                'payment_programs'
            );

            $lastProgramNo = $query->orderByDesc('id')->value('program_no');

            $lastNumeric = 0;
            if ($lastProgramNo && preg_match('/(\d+)$/', $lastProgramNo, $matches)) {
                $lastNumeric = (int) $matches[1];
            }

            $nextProgramNo = $lastNumeric + 1;

            $program = new PaymentProgram($branches->assignBranchOnCreate([
                'program_no' => app(BranchSerialService::class)->formatBranchDocumentNumber(
                    (string) $nextProgramNo,
                    'payment_programs',
                    $branches->selectedBranchForModule('payment_programs')
                ),
                'date' => $request->date,
                'customer_id' => $request->customer_id,
                'category' => $request->category,
                'amount' => $request->amount,
                'remarks' => $request->remarks,
            ], 'payment_programs'));

            if ($subCategoryModel) {
                $subCategoryModel->paymentPrograms()->save($program);
            } else {
                $program->save();
            }
        });

        return redirect()->route('payment-programs.create')->with('success', 'Payment program added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentProgram $paymentProgram)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($paymentProgram, 'payment_programs');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentProgram $paymentProgram)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($paymentProgram, 'payment_programs');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        if ($request->filled('program_id')) {
            app(ModuleBranchService::class)->assertRecordInAllowedBranch(PaymentProgram::findOrFail($request->program_id), 'payment_programs');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentProgram $paymentProgram)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($paymentProgram, 'payment_programs');
    }
    public function updateProgram(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'program_id' => 'required|integer|exists:payment_programs,id',
            'category' => 'required|in:supplier,self_account,customer,waiting',
            'sub_category' => 'nullable|integer',
            'remarks' => 'nullable|string',
            'amount' => 'nullable|integer|min:1',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->category === 'waiting') {
                return;
            }

            if (blank($request->sub_category)) {
                $validator->errors()->add('sub_category', 'Please select a valid category account.');
                return;
            }

            if (! $this->resolveSubCategoryModel((string) $request->category, (int) $request->sub_category)) {
                $validator->errors()->add('sub_category', 'The selected category account is not available.');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }

        $program = PaymentProgram::findOrFail($request->program_id);
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($program, 'payment_programs');
        $subCategoryModel = $this->resolveSubCategoryModel($request->category, $request->sub_category);

        $program->category = $request->category;
        $program->remarks = $request->remarks;
        if ($request->amount !== null) {
            $program->amount = $request->amount;
        }

        if ($subCategoryModel) {
            $subCategoryModel->paymentPrograms()->save($program);
        } else {
            $program->save();
        }

        return redirect()->route('payment-programs.index')->with('success', 'Program updated successfully.');
    }

    public function markPaid($id)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $program = PaymentProgram::findOrFail($id);
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($program, 'payment_programs');
        $paidAmount = (float) $program->customerPayments()->sum('amount');
        $balance = (float) $program->amount - $paidAmount;

        $program->status = $balance < 0 ? 'Overpaid' : 'Paid';
        $program->save();

        return redirect()->route('payment-programs.index')->with('success', 'Program status updated successfully.');
    }

    public function CustomerSummary(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $branches = app(ModuleBranchService::class);
            $customersQuery = $branches->applyRelatedScope(Customer::whereHas('paymentPrograms', function ($q) {
                    app(ModuleBranchService::class)->applyScope($q, 'payment_programs')
                        ->where('status', 'Unpaid');
                }), 'customers', 'payment_programs')
                ->with([
                    'city:id,title,short_title',

                    'paymentPrograms' => function ($q) use ($branches) {
                        $this->branchScopedPaymentProgramQuery($q, $branches);

                        $q->where('status', 'Unpaid')
                            ->select('id', 'customer_id', 'amount', 'status', 'branch_id')

                            // customer paid amount
                            ->withSum('customerPayments as paid_amount', 'amount')

                            // supplier pending voucher payment
                            ->withSum([
                                'supplierPayments as voucher_pending_payment' => fn($sq) => $sq
                                    ->whereIn('method', ['program', 'Program'])
                                    ->whereNull('voucher_id'),
                            ], 'amount');
                    },
                ])
                ->applyFilters($request, false, true);

            $customers = $customersQuery->get()->map(function ($customer) {

                $customer->paymentPrograms->transform(function ($program) {

                    $program->setAppends([]);

                    $programAmount = (float) $program->amount;

                    $paidAmount = (float) ($program->paid_amount ?? 0);

                    $voucherPendingPayment = (float) ($program->voucher_pending_payment ?? 0);

                    $balance = max(0, $programAmount - $paidAmount);

                    $program->setAttribute('program_amount', $programAmount);

                    $program->setAttribute('payment', $voucherPendingPayment);

                    $program->setAttribute('balance', $balance);

                    return $program;
                });

                return [
                    'id' => $customer->id,
                    'name' => $customer->customer_name,
                    'name_city' => $customer->customer_name . ' | ' . ($customer->city?->title ?? '-'),
                    'data' => [
                        'payment_programs' => $customer->paymentPrograms->map(fn($program) => [
                            'id' => $program->id,
                            'amount' => (float) $program->program_amount,
                            'payment' => (float) $program->payment,
                            'balance' => (float) $program->balance,
                            'status' => $program->status,
                        ])->values(),
                    ],
                ];
            });

            return response()->json(['data' => $customers, 'authLayout' => $authLayout]);
        }

        return view('payment-programs.customerSummary', compact('authLayout'));
    }

    public function SupplierSummary(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $branches = app(ModuleBranchService::class);
            $openProgramQuery = function ($q) use ($branches) {
                $this->branchScopedPaymentProgramQuery($q, $branches);

                return $q->where(function ($qq) {
                    $qq->where('status', 'Unpaid')
                        ->orWhereHas('supplierPayments', fn($sq) => $sq
                            ->whereIn('method', ['program', 'Program'])
                            ->whereNull('voucher_id'));
                });
            };

            $suppliersQuery = $branches->applyRelatedScope(Supplier::whereHas('paymentPrograms', $openProgramQuery), 'suppliers', 'payment_programs')
                ->with([
                    'paymentPrograms' => fn($q) => $openProgramQuery($q)
                        ->select(
                            'id',
                            'sub_category_id',
                            'sub_category_type',
                            'amount',
                            'status',
                            'branch_id'
                        )

                        // customer paid amount
                        ->withSum('customerPayments as paid_amount', 'amount')

                        // ONLY pending supplier payments
                        ->withSum([
                            'supplierPayments as voucher_pending_payment' => fn($sq) => $sq
                                ->whereIn('method', ['program', 'Program'])
                                ->whereNull('voucher_id'),
                        ], 'amount'),
                ])
                ->applyFilters($request, false, true);

            $suppliers = $suppliersQuery->get()->map(function ($supplier) {
                $supplier->paymentPrograms->transform(function ($program) {
                    $program->setAppends([]);

                    $originalAmount = (float) $program->getRawOriginal('amount');
                    $paidAmount = (float) ($program->paid_amount ?? 0);
                    $pendingPayment = (float) ($program->voucher_pending_payment ?? 0);
                    $balance = max(0, $originalAmount - $paidAmount);

                    $program->setAttribute('program_amount', $originalAmount);
                    $program->setAttribute('payment', $pendingPayment);
                    $program->setAttribute('balance', $balance);

                    return $program;
                });

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->supplier_name,
                    'data' => [
                        'payment_programs' => $supplier->paymentPrograms->map(fn($program) => [
                            'id' => $program->id,
                            'amount' => (float) $program->program_amount,
                            'payment' => (float) $program->payment,
                            'balance' => (float) $program->balance,
                            'status' => $program->status,
                        ])->values(),
                    ],
                ];
            });

            return response()->json([
                'data' => $suppliers,
                'authLayout' => $authLayout
            ]);
        }

        return view('payment-programs.supplierSummary', compact('authLayout'));
    }

    private function branchScopedPaymentProgramQuery(
        mixed $query,
        ModuleBranchService $branches,
    ): \Illuminate\Database\Eloquent\Builder {
        if ($query instanceof \Illuminate\Database\Eloquent\Builder) {
            return $branches->applyScope($query, 'payment_programs');
        }

        if (method_exists($query, 'getQuery')) {
            $builder = $query->getQuery();
            if ($builder instanceof \Illuminate\Database\Eloquent\Builder) {
                return $branches->applyScope($builder, 'payment_programs');
            }
        }

        throw new \InvalidArgumentException('Payment program query could not be branch scoped.');
    }

    private function resolveSubCategoryModel(string $category, ?int $subCategoryId): Supplier|BankAccount|Customer|null
    {
        if ($category === 'waiting' || !$subCategoryId) {
            return null;
        }

        return match ($category) {
            'supplier' => app(ModuleBranchService::class)->applyRelatedScope(Supplier::query(), 'suppliers', 'payment_programs')->find($subCategoryId),
            'self_account' => app(ModuleBranchService::class)->applyRelatedScope(BankAccount::query(), 'bank_accounts', 'payment_programs')->find($subCategoryId),
            'customer' => app(ModuleBranchService::class)->applyRelatedScope(Customer::query(), 'customers', 'payment_programs')->find($subCategoryId),
            default => null,
        };
    }
}
