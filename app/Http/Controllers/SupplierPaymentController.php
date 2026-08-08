<?php

namespace App\Http\Controllers;

use App\Models\SupplierPayment;
use App\Models\Customer;
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
                'bankAccount.bank',
                'selfAccount.bank',
                'cheque.paymentClearRecord.bankAccount.bank',
                'slip.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.bankAccount.bank',
                'program.customer.city',
                'cheque.customer.city',
                'slip.customer.city',
                'voucher',
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
            $supplierPayment->delete();
        });

        return redirect()->route('supplier-payments.index')->with('success', 'Supplier payment deleted successfully.');
    }
}
