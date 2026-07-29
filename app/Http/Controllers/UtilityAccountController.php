<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Models\UtilityAccount;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;

class UtilityAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        // $utilityAccounts = UtilityAccount::with('billType', 'location')->get();

        if ($request->ajax()) {
            $utilityAccounts = app(ModuleBranchService::class)
                ->applyScope(UtilityAccount::orderByDesc('id'), 'utility_accounts')
                ->applyFilters($request);

            return response()->json(['data' => $utilityAccounts, 'authLayout' => $authLayout]);
        }

        return view('utility-accounts.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        [$bill_type_options, $location_options] = $this->utilityAccountFormOptions();

        return view('utility-accounts.add', compact('bill_type_options', 'location_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->validate([
            'bill_type_id' => 'required|integer|exists:setups,id',
            'location_id' => 'required|integer|exists:setups,id',
            'account_title' => 'required|string|max:200',
            'account_no' => 'required|string|max:200'
        ]);

        $branches = app(ModuleBranchService::class);
        $billType = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_accounts')->find($request->bill_type_id);
        $location = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_accounts')->find($request->location_id);
        if (!$billType || !$location) {
            return redirect()->back()->withErrors(['bill_type_id' => 'Selected bill type/location is not available for this branch.'])->withInput();
        }

        UtilityAccount::create($branches->assignBranchOnCreate([
            'bill_type_id' => $request->bill_type_id,
            'location_id' => $request->location_id,
            'account_title' => $request->account_title,
            'account_no' => $request->account_no,
        ], 'utility_accounts'));

        return redirect()->back()->with('success', 'Utility account added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(UtilityAccount $utilityAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UtilityAccount $utilityAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');

        [$bill_type_options, $location_options] = $this->utilityAccountFormOptions();

        return view('utility-accounts.add', compact('bill_type_options', 'location_options', 'utilityAccount'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UtilityAccount $utilityAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');

        $request->validate([
            'bill_type_id' => 'required|integer|exists:setups,id',
            'location_id' => 'required|integer|exists:setups,id',
            'account_title' => 'required|string|max:200',
            'account_no' => 'required|string|max:200'
        ]);

        $branches = app(ModuleBranchService::class);
        $billType = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_accounts')->find($request->bill_type_id);
        $location = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_accounts')->find($request->location_id);
        if (!$billType || !$location) {
            return redirect()->back()->withErrors(['bill_type_id' => 'Selected bill type/location is not available for this branch.'])->withInput();
        }

        $utilityAccount->update([
            'bill_type_id' => $request->bill_type_id,
            'location_id' => $request->location_id,
            'account_title' => $request->account_title,
            'account_no' => $request->account_no,
        ]);

        return redirect()->route('utility-accounts.index')->with('success', 'Utility account updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UtilityAccount $utilityAccount)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');

        $dependencies = $this->dependencyCounts([
            'utility bills' => ['utility_bills', 'account_id', $utilityAccount->id],
        ]);

        if ($dependencies !== []) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Utility account', $dependencies));
        }

        $utilityAccount->delete();

        return redirect()->route('utility-accounts.index')->with('success', 'Utility account deleted successfully.');
    }

    private function utilityAccountFormOptions(): array
    {
        $bill_type_options = [];
        $location_options = [];

        $branches = app(ModuleBranchService::class);
        $bill_types = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_accounts')->get();
        $locations = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_accounts')->get();

        foreach ($bill_types as $type) {
            $bill_type_options[(int) $type->id] = [
                'text' => $type->title,
            ];
        }

        foreach ($locations as $location) {
            $location_options[(int) $location->id] = [
                'text' => $location->title,
            ];
        }

        return [$bill_type_options, $location_options];
    }
}
