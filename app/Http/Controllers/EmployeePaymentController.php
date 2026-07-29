<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayment;
use App\Models\Employee;
use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;

class EmployeePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        // $payments = EmployeePayment::with('employee.type')->get();

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $payments = app(ModuleBranchService::class)
                ->applyScope(EmployeePayment::orderByDesc('id'), 'employee_payments')
                ->applyFilters($request);

            return response()->json(['data' => $payments, 'authLayout' => $authLayout]);
        }

        $branches = app(ModuleBranchService::class);
        $staff = $branches->applyRelatedScope(Setup::where('type', 'staff_type'), 'setups', 'employee_payments')->get()
            ->mapWithKeys(fn ($type) => [
                $type->id => [
                    'text' => $type->title,
                    'category' => 'staff',
                ],
            ])
            ->all();

        $worker = $branches->applyRelatedScope(Setup::where('type', 'worker_type'), 'setups', 'employee_payments')->get()
            ->mapWithKeys(fn ($type) => [
                $type->id => [
                    'text' => $type->title,
                    'category' => 'worker',
                ],
            ])
            ->all();

        $all_types = $staff + $worker;

        return view('employee-payments.index', compact('all_types', 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        return view('employee-payments.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
            'method' => 'required|string',
            'amount' => 'required|integer|min:1',
        ]);

        $employee = app(ModuleBranchService::class)->applyRelatedScope(Employee::query(), 'employees', 'employee_payments')->find($request->employee_id);
        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'Selected employee is not available for this branch.'])->withInput();
        }

        EmployeePayment::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'employee_id' => $request->employee_id,
            'date' => $request->date,
            'method' => $request->method,
            'amount' => $request->amount,
        ], 'employee_payments'));

        return redirect()->back()->with('success', 'Payment added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeePayment $employeePayment)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($employeePayment, 'employee_payments');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeePayment $employeePayment)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($employeePayment, 'employee_payments');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $employeePayment->load('employee.type');

        return view('employee-payments.edit', compact('employeePayment'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeePayment $employeePayment)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($employeePayment, 'employee_payments');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
            'method' => 'required|string|in:cash,adjustment',
            'amount' => 'required|integer|min:1',
        ]);

        $employee = app(ModuleBranchService::class)->applyRelatedScope(Employee::query(), 'employees', 'employee_payments')->find($request->employee_id);
        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'Selected employee is not available for this branch.'])->withInput();
        }

        $employeePayment->update([
            'employee_id' => $request->employee_id,
            'date' => $request->date,
            'method' => $request->method,
            'amount' => $request->amount,
        ]);

        return redirect()->route('employee-payments.index')->with('success', 'Payment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeePayment $employeePayment)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($employeePayment, 'employee_payments');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $employeePayment->delete();

        return redirect()->route('employee-payments.index')->with('success', 'Payment deleted successfully.');
    }
}
