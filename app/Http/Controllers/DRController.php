<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DR;
use App\Models\Setup;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DRController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $drs = DR::with('customer.city')->orderBy('id', 'desc')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $drs = app(ModuleBranchService::class)
                ->applyScope(DR::orderByDesc('id'), 'dr')
                ->applyFilters($request);

            return response()->json(['data' => $drs, 'authLayout' => $authLayout]);
        }

        return view('dr.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $customer_options = $branches->applyRelatedScope(Customer::select('id', 'customer_name', 'city_id'), 'customers', 'dr')
            ->distinct()
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('customer_name')
            ->get()
            ->mapWithKeys(function ($customer) {
                return [
                    (int)$customer->id => [
                        'text' => ucfirst($customer->customer_name) . ' | ' . strtoupper($customer->city->short_title),
                    ]
                ];
            });

        $bank_options = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'dr')
            ->distinct()
            ->orderBy('title')
            ->get()
            ->mapWithKeys(function ($bank) {
                return [
                    (int)$bank->id => [
                        'text' => ucfirst($bank->title),
                    ]
                ];
            });

        return view('dr.generate', compact('customer_options', 'bank_options'));
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
            'customer_id' => 'required|integer|exists:customers,id',
            'date' => 'required|date',
            'returnPayments' => 'required|string',
            'newPayments' => 'required|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'customer_id' => $request->customer_id,
            'date' => $request->date,
            'return_payments' => json_decode($request->returnPayments ?? '[]'),
            'new_payments_data' => json_decode($request->newPayments ?? '[]'),
        ];

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments_data']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select returned payments and add replacement payments.');
        }

        if ($returnEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select at least one returned payment.');
        }

        if ($newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please add at least one replacement payment.');
        }

        $data['new_payments'] = [];

        $branches = app(ModuleBranchService::class);
        $customer = $branches->applyRelatedScope(Customer::query(), 'customers', 'dr')->find($data['customer_id']);
        if (!$customer) {
            return redirect()->back()->withErrors(['customer_id' => 'Selected customer is not available for this branch.'])->withInput();
        }

        $dr = new DR($branches->assignBranchOnCreate($data, 'dr'));
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye
        $dr->d_r_no = app(BranchSerialService::class)->formatBranchDocumentNumber(
            $dr->id,
            'dr',
            $branches->selectedBranchForModule('dr')
        );
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye

        foreach($data['return_payments'] as $paymentId) {
            $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'dr')
                ->findOrFail($paymentId)
                ->update(['clear_date' => $data['date'], 'd_r_id' => $dr->id]);
        }

        foreach ($data['new_payments_data'] as $payment) {
            $newPayment = CustomerPayment::create($branches->assignBranchOnCreate([
                'customer_id'     => $data['customer_id'],
                'date'            => $payment->date ?? $data['date'],
                'type'            => 'DR',
                'method'          => strtolower($payment->method),
                'amount'          => $payment->amount,
                'cheque_no'          => $payment->cheque_no ?? null,
                'slip_no'          => $payment->slip_no ?? null,
                'transaction_id'          => $payment->transaction_id ?? null,
                'cheque_date'          => $payment->cheque_date ?? null,
                'slip_date'          => $payment->slip_date ?? null,
                'bank_id'          => $payment->bank_id ?? null,
                'remarks'          => $payment->remarks ?? null,
            ], 'customer_payments'));

            $data['new_payments'][] = $newPayment->id;
        }

        $dr->new_payments = $data['new_payments'];
        $dr->save(); // 👈 dubara save karenge taake new_payments update ho jaye

        return redirect()->route('dr.create')->with('success', 'DR Generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DR $dR)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DR $dR)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DR $dR)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DR $dR)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');
    }

    public function getPayments(Request $request)
    {
        $payments = app(ModuleBranchService::class)
            ->applyRelatedScope(CustomerPayment::where('customer_id', $request->customer_id), 'customer_payments', 'dr')
            ->whereIn('method', ['cheque', 'slip'])
            ->whereNull('d_r_id')
            ->where('is_return', true)
            ->get();

        return response()->json(['status' => 'success', 'data' => $payments]);
    }
}
