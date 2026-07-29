<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect(route('rates.create'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $type_options = [];
        $workerTypes = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'worker_type'), 'setups', 'rates')->get();
        foreach($workerTypes as $workerType) {
            $type_options[(int)$workerType->id] = [
                'text' => $workerType->title
            ];
        }

        return view('rates.add', compact('type_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'type_id' => 'required|integer|exists:setups,id',
            'effective_date' => 'required|date',
            'categories' => 'required|string', 
            'seasons' => 'required|string',
            'sizes' => 'required|string',
            'title' => 'required|string|unique:rates,title',
            'rate' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $type = app(ModuleBranchService::class)->applyRelatedScope(Setup::where('type', 'worker_type'), 'setups', 'rates')->find($request->type_id);
        if (!$type) {
            return redirect()->back()->withErrors(['type_id' => 'Selected worker type is not available for this branch.'])->withInput();
        }

        $rate = Rate::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'type_id' => $request['type_id'],
            'effective_date' => $request['effective_date'],
            'categories' => json_decode($request['categories'], true),
            'seasons' => json_decode($request['seasons'], true),
            'sizes' => json_decode($request['sizes'], true),
            'title' => $request['title'],
            'rate' => $request['rate'],
        ], 'rates'));

        return redirect()->route('rates.create')->with('success', 'Rates Added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Rate $rate)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($rate, 'rates');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rate $rate)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($rate, 'rates');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rate $rate)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($rate, 'rates');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rate $rate)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($rate, 'rates');

        $rate->delete();

        return redirect()->route('rates.index')->with('success', 'Rate deleted successfully.');
    }
}
