<?php

namespace App\Http\Controllers;

use App\Models\SupplierPayment;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupplierPaymentController extends Controller
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
            $payments = app(ModuleBranchService::class)->applyScope(SupplierPayment::with([
                'supplier',
                'bankAccount.bank',
                'selfAccount.bank',
                'cheque.paymentClearRecord.bankAccount.bank',
                'slip.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.bankAccount.bank',
                'program.customer.city',
                'cheque.customer.city',
                'cheque.dr',
                'slip.customer.city',
                'slip.dr',
                'voucher',
                'cr',
            ])->orderByDesc('id'), 'supplier_payments')->applyFilters($request);

            return response()->json(['data' => $payments, 'authLayout' => $authLayout]);
        }

        return view("supplier-payments.index", compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierPayment $supplierPayment)
{
    app(ModuleBranchService::class)->assertRecordInAllowedBranch($supplierPayment, 'supplier_payments');

    if ($resp = $this->denyIfNoRole(['developer'])) {
        return $resp;
    }

    $dependencies = [];
    if ($supplierPayment->voucher_id) {
        $dependencies['voucher'] = 1;
    }
    if ($supplierPayment->c_r_id) {
        $dependencies['credit return'] = 1;
    }

    if (!empty($dependencies)) {
        return redirect()->back()->with('error', $this->dependencyBlockMessage('Supplier payment', $dependencies));
    }

    DB::transaction(function () use ($supplierPayment) {
        $method = strtolower(str_replace([' ', '_', '.'], '', (string) $supplierPayment->method));

        // Real customer cheque/slip → unlink (it existed before this record, don't delete it)
        if ($method === 'cheque' && $supplierPayment->cheque_id) {
            CustomerPayment::where('id', $supplierPayment->cheque_id)->update([
                'bank_account_id' => null,
                'is_return' => false,
            ]);
        } elseif ($method === 'slip' && $supplierPayment->slip_id) {
            CustomerPayment::where('id', $supplierPayment->slip_id)->update([
                'bank_account_id' => null,
                'is_return' => false,
            ]);
        }
        // Self Cheque / ATM → linked CustomerPayment was only created for this record
        elseif (in_array($method, ['selfcheque', 'atm']) && $supplierPayment->cheque_id) {
            CustomerPayment::where('id', $supplierPayment->cheque_id)
                ->where('type', 'self_account_deposit')
                ->delete();
        }
        // Cash / Adjustment self deposit → find & remove the auto-created CustomerPayment
        elseif (in_array($method, ['cash', 'adjustment']) && $supplierPayment->self_account_id) {
            CustomerPayment::where([
                'type' => 'self_account_deposit',
                'method' => $supplierPayment->method,
                'amount' => $supplierPayment->amount,
                'bank_account_id' => $supplierPayment->self_account_id,
            ])
                ->whereDate('date', $supplierPayment->date)
                ->where('remarks', $supplierPayment->remarks)
                ->latest('id')
                ->first()
                ?->delete();
        }

        $supplierPayment->delete();
    });

    return redirect()->route('supplier-payments.index')->with('success', 'Supplier payment deleted successfully.');
}
}
