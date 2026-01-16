<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Models\UtilityBill;
use Illuminate\Http\Request;

class UtilityBillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // $utilityBills = UtilityBill::with('account.billType', 'account.location')->get();

        if ($request->ajax()) {
            $utilityBills = UtilityBill::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $utilityBills, 'authLayout' => 'table']);
        }

        return view('utility-bills.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $bill_type_options = [];
        $location_options = [];

        $bill_types = Setup::where('type', 'utility_bill_type')->get();
        $locations = Setup::where('type', 'utility_bill_location')->get();

        foreach($bill_types as $type) {
            $bill_type_options[(int)$type->id] = [
                'text' => $type->title
            ];
        }

        foreach($locations as $location) {
            $location_options[(int)$location->id] = [
                'text' => $location->title
            ];
        }

        return view('utility-bills.add', compact('bill_type_options', 'location_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $request->validate([
            'account_id' => 'required|integer|exists:utility_accounts,id',
            'month' => 'required|date_format:Y-m',
            'units' => 'nullable|integer',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'required|date',
        ]);

        $data = $request->all();

        UtilityBill::create($data);

        return redirect()->back()->with('success', 'Utility Bill added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(UtilityBill $utility)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UtilityBill $utility)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UtilityBill $utility)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UtilityBill $utility)
    {
        //
    }

    public function markPaid(Request $request, UtilityBill $utilityBill)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $utilityBill->is_paid = true;
        $utilityBill->save();

        return response()->json(['success', 'message' => 'Bill marked as paid successfully.']);
    }
}
