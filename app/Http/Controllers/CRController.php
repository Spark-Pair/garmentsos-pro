<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CR;
use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\Voucher;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CRController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $crs = CR::with('voucher.supplier')->orderBy('id', 'desc')->get()->makeHidden('creator');
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $crs = app(ModuleBranchService::class)
                ->applyScope(CR::orderByDesc('id'), 'cr')
                ->applyFilters($request);

            return response()->json(['data' => $crs, 'authLayout' => $authLayout]);
        }

        return view('cr.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $voucher_options = [];
        $payment_options = [];

        $supplier_id = $request->supplier;
        $method = $request->method;
        // The date field is populated after voucher selection; keep the
        // options endpoint safe when a method is changed before that value
        // reaches the request (especially on the edit wizard).
        $maxDate = $request->max_date ?: today()->toDateString();
        $payment_options = [];
        $branches = app(ModuleBranchService::class);

        if (Auth::user()->c_r_type == 'voucher') {
            $vouchers = $branches->applyRelatedScope(Voucher::query(), 'vouchers', 'cr')->get();

            if ($vouchers) {
                foreach($vouchers as $voucher) {
                    $voucher_options[$voucher->voucher_no] = [
                        'text' => $voucher->voucher_no
                    ];
                }
            }
        } else {
            $CRs = $branches->applyScope(CR::query(), 'cr')->get();

            if ($CRs) {
                foreach($CRs as $CR) {
                    $voucher_options[$CR->c_r_no] = [
                        'text' => $CR->c_r_no
                    ];
                }
            }
        }

        if ($method === 'cheque') {
            $cheques = $branches->applyRelatedScope(CustomerPayment::whereNotNull('cheque_no')->with('customer.city'), 'customer_payments', 'cr')->whereDoesntHave('cheque')->whereNull('bank_account_id')->whereDate('date', '<=', $maxDate)->get()->makeHidden('creator');

            foreach ($cheques as $cheque) {
                $payment_options[(int)$cheque->id] = [
                    'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                    'data_option' => $this->formatCustomerPaymentOptionPayload($cheque),
                ];
            }
        } else if ($method === 'slip') {
            $slips = $branches->applyRelatedScope(CustomerPayment::whereNotNull('slip_no')->with('customer.city'), 'customer_payments', 'cr')->whereDoesntHave('slip')->whereNull('bank_account_id')->whereDate('date', '<=', $maxDate)->get()->makeHidden('creator');

            foreach ($slips as $slip) {
                $payment_options[(int)$slip->id] = [
                    'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                    'data_option' => $this->formatCustomerPaymentOptionPayload($slip),
                ];
            }
        } else if ($method === 'self_cheque') {
            $self_accounts = $branches->applyRelatedScope(BankAccount::where('category', 'self')->with('bank:id,title,short_title'), 'bank_accounts', 'cr')->get();

            // Do not use BankAccount::$available_cheques here: that accessor
            // performs one SupplierPayment query per account. The CR method
            // endpoint is called while typing/selecting, so batch this once.
            $selfAccountIds = $self_accounts->pluck('id')->map(fn ($id) => (int) $id)->values();
            $usedChequesByAccount = $selfAccountIds->isEmpty()
                ? collect()
                : SupplierPayment::whereIn('bank_account_id', $selfAccountIds)
                    ->with('cheque:id,cheque_no')
                    ->get(['bank_account_id', 'cheque_id', 'cheque_no'])
                    ->map(fn ($payment) => [
                        'account_id' => (int) $payment->bank_account_id,
                        'cheque_no' => (int) ($payment->cheque_no ?: $payment->cheque?->cheque_no),
                    ])
                    ->filter(fn ($payment) => $payment['cheque_no'] > 0)
                    ->groupBy('account_id')
                    ->map(fn ($payments) => $payments->pluck('cheque_no')->unique()->values()->all());
            $bankAccountPayloads = $self_accounts->mapWithKeys(fn ($account) => [
                (int) $account->id => $this->formatBankAccountOptionPayload($account),
            ]);

            foreach ($self_accounts as $self_account) {
                $start = (int) $self_account->chqbk_serial_start;
                $end = (int) $self_account->chqbk_serial_end;
                $allCheques = ($start > 0 && $end >= $start) ? range($start, $end) : [];
                $usedCheques = $usedChequesByAccount->get((int) $self_account->id, []);

                foreach (array_values(array_diff($allCheques, $usedCheques)) as $available_cheque) {
                    $accountParts = explode('|', (string) $self_account->account_title, 2);
                    $accountLabel = trim($accountParts[1] ?? $accountParts[0]);
                    $payment_options[(int)$available_cheque] = [
                        'text' => $available_cheque . ' | ' . $accountLabel,
                        'data_option' => $bankAccountPayloads->get((int) $self_account->id),
                    ];
                }
            }
        } else if ($method === 'program') {
            $payments = $branches->applyRelatedScope(SupplierPayment::where('supplier_id', $supplier_id), 'supplier_payments', 'cr')
                ->with('program.customer.city:id,title,short_title')
                ->where('method', 'program')
                ->whereNull('voucher_id')
                ->whereDate('date', '<=', $maxDate)
                ->get();

            foreach ($payments as $payment) {
                $payment_options[(int)$payment->id] = [
                    'text' => 'Rs. ' . \App\Support\Money::format($payment->amount) . ' | ' . $payment->program->customer->customer_name . ' | ' . $payment->program->customer->city->short_title,
                    'data_option' => $this->formatSupplierPaymentOptionPayload($payment),
                ];
            }
        }

        return view('cr.generate', compact('payment_options', 'voucher_options'));
    }

    private function formatCustomerPaymentOptionPayload(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'amount' => $payment->amount,
            'bank_account_id' => $payment->bank_account_id,
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'reff_no' => $payment->cheque_no ?? $payment->slip_no ?? $payment->transaction_id ?? $payment->reff_no,
            'cheque_date' => $payment->cheque_date?->format('Y-m-d'),
            'slip_date' => $payment->slip_date?->format('Y-m-d'),
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'customer_name' => $payment->customer->customer_name,
                'city' => [
                    'id' => $payment->customer->city?->id,
                    'title' => $payment->customer->city?->title,
                    'short_title' => $payment->customer->city?->short_title,
                ],
            ] : null,
        ];
    }

    private function formatSupplierPaymentOptionPayload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'amount' => $payment->amount,
            'bank_account_id' => $payment->bank_account_id,
            'transaction_id' => $payment->transaction_id,
            'program_id' => $payment->program_id,
        ];
    }

    private function formatBankAccountOptionPayload(BankAccount $account): array
    {
        return $this->bankAccountOptionPayload($account);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'voucher_no' => 'required|string',
            'voucher_id' => 'required|integer|exists:vouchers,id',
            'c_r_no' => 'required|string',
            'returnPayments' => 'required|string',
            'newPayments' => 'required|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'date' => $request->date,
            'voucher_no' => $request->voucher_no,
            'voucher_id' => $request->voucher_id,
            'c_r_no' => $request->c_r_no,
            'return_payments' => json_decode($request->returnPayments ?? '[]'),
            'new_payments' => json_decode($request->newPayments ?? '[]'),
        ];

        // if (!str_starts_with($data['c_r_no'], 'CR-')) {
        //     $data['c_r_no'] = 'CR-' . $data['c_r_no'];
        // }
        $data['c_r_no'] = app(BranchSerialService::class)->formatBranchDocumentNumber(
            $data['c_r_no'],
            'cr',
            app(ModuleBranchService::class)->selectedBranchForModule('cr')
        );

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select returned payments and add replacement payments.');
        }

        if ($returnEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select at least one returned payment.');
        }

        if ($newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please add at least one replacement payment.');
        }

        foreach($data['return_payments'] as $payment) {
            $branches = app(ModuleBranchService::class);
            $branches->applyRelatedScope(SupplierPayment::query(), 'supplier_payments', 'cr')
                ->findOrFail($payment->id)
                ->update(['is_return' => true]);
            $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'cr')
                ->findOrFail($payment->payment_id)
                ->update(['is_return' => true]);
        }

        $branches = app(ModuleBranchService::class);
        $voucher = $branches->applyRelatedScope(Voucher::query(), 'vouchers', 'cr')->find($data['voucher_id']);
        if (!$voucher) {
            return redirect()->back()->withErrors(['voucher_id' => 'Selected voucher is not available for this branch.'])->withInput();
        }

        $cr = new CR($branches->assignBranchOnCreate($data, 'cr'));
        $cr->save(); // 👈 pehle save karenge taake $cr->id mil jaye

        foreach ($data['new_payments'] as $payment) {
            if ($payment->method == 'Payment Program') {
                $branches->applyRelatedScope(SupplierPayment::query(), 'supplier_payments', 'cr')
                    ->find($payment->data_value)
                    ?->update([
                        'method' => $payment->method . ' | CR',
                        'c_r_id' => $cr->id,
                    ]);
                $payment->payment_id = (int) $payment->data_value;
            } else {
                $columnMap = [
                    'Self Cheque' => 'cheque_no',
                    'Cheque'      => 'cheque_id',
                    'Slip'        => 'slip_id',
                ];

                // Skip unknown methods
                if (!isset($columnMap[$payment->method])) {
                    continue;
                }

                $newSupplierPayment = SupplierPayment::create($branches->assignBranchOnCreate([
                    'supplier_id'      => $voucher->supplier_id,
                    'date'             => $data['date'],
                    'method'           => $payment->method . ' | CR',
                    'amount'           => $payment->amount,
                    'bank_account_id'  => $payment->bank_account_id ?? null,
                    'voucher_id'       => null,
                    'c_r_id'           => $cr->id, // 👈 ab yahan id set ho jaegi
                    $columnMap[$payment->method] => $payment->data_value,
                ], 'supplier_payments'));

                $payment->payment_id = $newSupplierPayment->id;
            }
        }

        $cr->new_payments = $data['new_payments'];
        $cr->save(); // 👈 dubara save karenge taake new_payments update ho jaye

        return redirect()->route('cr.create')->with('success', 'CR Generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch(CR::findOrFail($id), 'cr');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $cr = CR::with('voucher.supplier')->findOrFail($id);
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cr, 'cr');

        return view('cr.generate', [
            'cr' => $cr,
            'payment_options' => [],
            'voucher_options' => [],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $cr = CR::findOrFail($id);
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cr, 'cr');

        $validated = $request->validate([
            'date' => 'required|date',
            'c_r_no' => 'required|string|max:255',
            'voucher_id' => 'nullable|integer|exists:vouchers,id',
            'returnPayments' => 'nullable|string',
            'newPayments' => 'nullable|string',
        ]);

        $returnPayments = collect(json_decode((string) $request->input('returnPayments', ''), true) ?: ($cr->return_payments ?? []));
        $newPayments = collect(json_decode((string) $request->input('newPayments', ''), true) ?: ($cr->new_payments ?? []));
        if ($returnPayments->isEmpty() || $newPayments->isEmpty()) {
            return redirect()->back()->withInput()->with('error', 'Please select returned payments and add replacement payments.');
        }

        $branches = app(ModuleBranchService::class);
        $voucherId = $validated['voucher_id'] ?: $cr->voucher_id;
        $voucher = $branches->applyRelatedScope(Voucher::query(), 'vouchers', 'cr')->find($voucherId);
        if (!$voucher) {
            return redirect()->back()->withInput()->withErrors(['voucher_id' => 'Selected voucher is not available for this branch.']);
        }

        DB::transaction(function () use ($cr, $validated, $voucherId, $returnPayments, $newPayments, $branches, $voucher) {
            $paymentQuery = fn () => $branches->applyRelatedScope(SupplierPayment::query(), 'supplier_payments', 'cr');
            $customerPaymentQuery = fn () => $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'cr');

            $oldReturns = collect($cr->return_payments ?? []);
            $oldSupplierReturnIds = $oldReturns->map(fn ($payment) => data_get($payment, 'id'))->filter()->map(fn ($id) => (int) $id);
            $oldCustomerReturnIds = $oldReturns->map(fn ($payment) => data_get($payment, 'payment_id'))->filter()->map(fn ($id) => (int) $id);
            $newSupplierReturnIds = $returnPayments->map(fn ($payment) => data_get($payment, 'id') ?? data_get($payment, 'payment_id'))->filter()->map(fn ($id) => (int) $id);
            $newCustomerReturnIds = $returnPayments->map(fn ($payment) => data_get($payment, 'payment_id'))->filter()->map(fn ($id) => (int) $id);

            $oldSupplierReturnIds->diff($newSupplierReturnIds)->each(fn ($paymentId) => $paymentQuery()->whereKey($paymentId)->where('is_return', true)->update(['is_return' => false]));
            $oldCustomerReturnIds->diff($newCustomerReturnIds)->each(fn ($paymentId) => $customerPaymentQuery()->whereKey($paymentId)->where('is_return', true)->update(['is_return' => false]));
            $newSupplierReturnIds->each(fn ($paymentId) => $paymentQuery()->whereKey($paymentId)->update(['is_return' => true]));
            $newCustomerReturnIds->each(fn ($paymentId) => $customerPaymentQuery()->whereKey($paymentId)->update(['is_return' => true]));

            $oldGeneratedPayments = $paymentQuery()->where('c_r_id', $cr->id)->get();
            $retainedPaymentIds = collect();
            $savedPayments = [];
            $columnMap = [
                'Self Cheque' => 'cheque_no',
                'Cheque' => 'cheque_id',
                'Slip' => 'slip_id',
            ];

            foreach ($newPayments as $payment) {
                $method = trim((string) data_get($payment, 'method'));
                $paymentId = data_get($payment, 'payment_id');
                $existing = $paymentId ? $oldGeneratedPayments->firstWhere('id', (int) $paymentId) : null;

                if ($existing) {
                    $retainedPaymentIds->push($existing->id);
                    $payment['payment_id'] = $existing->id;
                    $savedPayments[] = $payment;
                    continue;
                }

                if ($method === 'Payment Program') {
                    $programPayment = $paymentQuery()->whereKey(data_get($payment, 'data_value'))
                        ->where('method', 'program')->first();
                    if ($programPayment) {
                        $programPayment->update(['method' => 'Payment Program | CR', 'c_r_id' => $cr->id]);
                        $retainedPaymentIds->push($programPayment->id);
                        $payment['payment_id'] = $programPayment->id;
                        $savedPayments[] = $payment;
                    }
                    continue;
                }

                if (!isset($columnMap[$method])) {
                    continue;
                }

                $created = SupplierPayment::create($branches->assignBranchOnCreate([
                    'supplier_id' => $voucher->supplier_id,
                    'date' => $validated['date'],
                    'method' => $method . ' | CR',
                    'amount' => data_get($payment, 'amount', 0),
                    'bank_account_id' => data_get($payment, 'bank_account_id'),
                    'voucher_id' => null,
                    'c_r_id' => $cr->id,
                    $columnMap[$method] => data_get($payment, 'data_value'),
                ], 'supplier_payments'));
                $retainedPaymentIds->push($created->id);
                $payment['payment_id'] = $created->id;
                $savedPayments[] = $payment;
            }

            $oldGeneratedPayments->reject(fn ($payment) => $retainedPaymentIds->contains($payment->id))->each(function (SupplierPayment $payment) {
                if (stripos((string) $payment->method, 'payment program') === 0) {
                    $payment->update(['method' => 'program', 'c_r_id' => null]);
                } else {
                    $payment->delete();
                }
            });

            $cr->update([
                'date' => $validated['date'],
                'c_r_no' => $validated['c_r_no'],
                'voucher_id' => $voucherId,
                'return_payments' => $returnPayments->all(),
                'new_payments' => $savedPayments,
            ]);
        });

        return redirect()->route('cr.index')->with('success', 'CR updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (Auth::user()?->role !== 'developer' && !app_can('cr', 'override')) {
            abort(403, 'Only a developer or developer-mode user can delete CR records.');
        }

        $cr = CR::findOrFail($id);
        $branches = app(ModuleBranchService::class);
        $branches->assertRecordInAllowedBranch($cr, 'cr');

        DB::transaction(function () use ($cr, $branches) {
            foreach (collect($cr->return_payments ?? []) as $payment) {
                $supplierPaymentId = data_get($payment, 'id') ?? data_get($payment, 'payment_id');
                $customerPaymentId = data_get($payment, 'payment_id');

                if ($supplierPaymentId) {
                    $branches->applyRelatedScope(SupplierPayment::query(), 'supplier_payments', 'cr')
                        ->whereKey($supplierPaymentId)
                        ->update(['is_return' => false]);
                }

                if ($customerPaymentId) {
                    $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'cr')
                        ->whereKey($customerPaymentId)
                        ->update(['is_return' => false]);
                }
            }

            $branches->applyRelatedScope(SupplierPayment::query(), 'supplier_payments', 'cr')
                ->where('c_r_id', $cr->id)
                ->get()
                ->each(function (SupplierPayment $payment) {
                    if (stripos((string) $payment->method, 'payment program') === 0) {
                        $payment->update(['method' => 'program', 'c_r_id' => null]);
                    } else {
                        $payment->delete();
                    }
                });

            $cr->delete();
        });

        return redirect()->route('cr.index')->with('success', 'CR deleted successfully.');
    }
}
