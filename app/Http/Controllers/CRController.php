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
        $maxDate = $request->max_date;
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

            foreach ($self_accounts as $self_account) {
                foreach ($self_account->available_cheques as $available_cheque) {
                    $payment_options[(int)$available_cheque] = [
                        'text' => $available_cheque . ' |' . explode('|', $self_account->account_title)[1],
                        'data_option' => $this->formatBankAccountOptionPayload($self_account),
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

        if (!str_starts_with($data['c_r_no'], 'CR-')) {
            $data['c_r_no'] = 'CR-' . $data['c_r_no'];
        }
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
                    ?->update(['method' => $payment->method . ' | CR']);
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
        app(ModuleBranchService::class)->assertRecordInAllowedBranch(CR::findOrFail($id), 'cr');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch(CR::findOrFail($id), 'cr');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch(CR::findOrFail($id), 'cr');
    }
}
