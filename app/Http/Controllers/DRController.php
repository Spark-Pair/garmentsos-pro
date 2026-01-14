<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DR;
use App\Models\Setup;
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
    public function index()
    {
        $drs = DR::with('customer.city')->orderBy('id', 'desc')->get();

        return view('dr.index', compact('drs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $customer_options = Customer::select('id', 'customer_name', 'city_id')
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

        $bank_options = Setup::where('type', 'bank_name')
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
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

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

        $data = $request->all();
        $data['return_payments'] = json_decode($data['returnPayments'] ?? '[]');
        $data['new_payments_data'] = json_decode($data['newPayments'] ?? '[]');

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments_data']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->with('error', 'Payments not selected and Payments not added.');
        }

        if ($returnEmpty) {
            return redirect()->back()->with('error', 'Payments not selected.');
        }

        if ($newEmpty) {
            return redirect()->back()->with('error', 'Payments not added.');
        }

        $data['new_payments'] = [];

        $dr = new DR($data);
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye
        $dr->d_r_no = 'DR-' . $dr->id;
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye

        foreach($data['return_payments'] as $paymentId) {
            CustomerPayment::find($paymentId)->update(['clear_date' => $data['date'], 'd_r_id' => $dr->id]);
        }

        foreach ($data['new_payments_data'] as $payment) {
            $newPayment = CustomerPayment::create([
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
            ]);

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
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DR $dR)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DR $dR)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DR $dR)
    {
        //
    }

    public function getPayments(Request $request)
    {
        $payments = CustomerPayment::where('customer_id', $request->customer_id)->whereIn('method', ['cheque', 'slip'])->whereNull('d_r_id')->where('is_return', true)->get();

        return response()->json(['status' => 'success', 'data' => $payments]);
    }
}
