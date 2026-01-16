<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CR;
use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\Voucher;
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

        if ($request->ajax()) {
            $crs = CR::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $crs, 'authLayout' => 'table']);
        }

        return view('cr.index');
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

        $voucher_options = [];
        $payment_options = [];

        $supplier_id = $request->supplier_id;
        $method = $request->method;
        $maxDate = $request->max_date;
        $payment_options = [];

        if (Auth::user()->c_r_type == 'voucher') {
            $vouchers = Voucher::all();

            if ($vouchers) {
                foreach($vouchers as $voucher) {
                    $voucher_options[$voucher->voucher_no] = [
                        'text' => $voucher->voucher_no
                    ];
                }
            }
        } else {
            $CRs = CR::all();

            if ($CRs) {
                foreach($CRs as $CR) {
                    $voucher_options[$CR->c_r_no] = [
                        'text' => $CR->c_r_no
                    ];
                }
            }
        }

        if ($method === 'cheque') {
            $cheques = CustomerPayment::whereNotNull('cheque_no')->with('customer.city')->whereDoesntHave('cheque')->whereNull('bank_account_id')->where('date', '<', $maxDate)->get()->makeHidden('creator');

            foreach ($cheques as $cheque) {
                $payment_options[(int)$cheque->id] = [
                    'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                    'data_option' => $cheque,
                ];
            }
        } else if ($method === 'slip') {
            $slips = CustomerPayment::whereNotNull('slip_no')->with('customer.city')->whereDoesntHave('slip')->whereNull('bank_account_id')->where('date', '<', $maxDate)->get()->makeHidden('creator');

            foreach ($slips as $slip) {
                $payment_options[(int)$slip->id] = [
                    'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                    'data_option' => $slip,
                ];
            }
        } else if ($method === 'self_cheque') {
            $self_accounts = BankAccount::where('category', 'self')->get()->makeHidden('creator');

            foreach ($self_accounts as $self_account) {
                foreach ($self_account->available_cheques as $available_cheque) {
                    $payment_options[(int)$available_cheque] = [
                        'text' => $available_cheque . ' |' . explode('|', $self_account->account_title)[1],
                        'data_option' => $self_account,
                    ];
                }
            }
        } else if ($method === 'program') {
            // ->whereBetween('date', [$voucherDate, $maxDate])
            $payments = SupplierPayment::where('supplier_id', $supplier_id)->where('method', 'program')->whereNull('voucher_id')->with('program.customer')->get()->makeHidden('creator');

            foreach ($payments as $payment) {
                $payment_options[(int)$payment->id] = [
                    'text' => ' | Rs. ' . number_format($payment->amount) . ' | ' . $payment->program->customer->customer_name . ' | ' . $payment->program->customer->city->short_title,
                    'data_option' => $payment,
                ];
            }
        }

        return view('cr.generate', compact('payment_options', 'voucher_options'));
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

        $data = $request->all();
        $data['return_payments'] = json_decode($data['returnPayments'] ?? '[]');
        $data['new_payments'] = json_decode($data['newPayments'] ?? '[]');

        if (!str_starts_with($data['c_r_no'], 'CR-')) {
            $data['c_r_no'] = 'CR-' . $data['c_r_no'];
        }

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->with('error', 'Payments not selected and Payments not added.');
        }

        if ($returnEmpty) {
            return redirect()->back()->with('error', 'Payments not selected.');
        }

        if ($newEmpty) {
            return redirect()->back()->with('error', 'Payments not added.');
        }

        foreach($data['return_payments'] as $payment) {
            SupplierPayment::find($payment->id)->update(['is_return' => true]);
            CustomerPayment::find($payment->payment_id)->update(['is_return' => true]);
        }

        $cr = new CR($data);
        $cr->save(); // 👈 pehle save karenge taake $cr->id mil jaye

        foreach ($data['new_payments'] as $payment) {
            if ($payment->method == 'Payment Program') {
                SupplierPayment::find($payment->data_value)
                    ->update(['method' => $payment->method . ' | CR']);
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

                $newSupplierPayment = SupplierPayment::create([
                    'supplier_id'      => Voucher::find($data['voucher_id'])->supplier_id,
                    'date'             => $data['date'],
                    'method'           => $payment->method . ' | CR',
                    'amount'           => $payment->amount,
                    'bank_account_id'  => $payment->bank_account_id || null,
                    'voucher_id'       => null,
                    'c_r_id'           => $cr->id, // 👈 ab yahan id set ho jaegi
                    $columnMap[$payment->method] => $payment->data_value,
                ]);

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
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
