<?php

namespace App\Http\Controllers;

use App\Models\DailyLedgerDeposit;
use App\Models\DailyLedgerUse;
use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $totalDeposit = DailyLedgerDeposit::orderByDesc('date')->applyFilters($request, false)->get()->map->toFormattedArray();
            $totalUse = DailyLedgerUse::orderByDesc('date')->applyFilters($request, false)->get()->map->toFormattedArray();

            $dailyLedgers = $totalDeposit
                ->merge($totalUse)
                ->sort(function ($a, $b) {

                    $aDate = $a['date'] ?? '1970-01-01';
                    $bDate = $b['date'] ?? '1970-01-01';

                    if ($aDate === $bDate) {
                        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
                    }

                    return strcmp($aDate, $bDate);
                })
                ->values();

            $balance = 0;

            $dailyLedgers = $dailyLedgers->reverse()->map(function ($row) use (&$balance) {
                $balance += $row['deposit'];
                $balance -= $row['use'];
                $row['balance'] = $balance;
                return $row;
            })->reverse()->values(); // reverse again to restore original order

            return response()->json(['data' => $dailyLedgers, 'authLayout' => 'table']);
        }

        return view('daily-ledger.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $totalDeposit = DailyLedgerDeposit::sum('amount');
        $totalUse = DailyLedgerUse::sum('amount');
        $balance = $totalDeposit - $totalUse;
        return view('daily-ledger.create', compact('balance'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $type = Auth::user()->daily_ledger_type;

        $commonRules = [
            'date'   => 'required|date',
            'amount' => 'required|integer',
        ];

        if ($type === 'deposit') {
            $rules = array_merge($commonRules, [
                'method'  => 'required|string',
                'reff_no' => 'nullable|string',
            ]);

            $validated = $request->validate($rules);

            DailyLedgerDeposit::create($request->only(['date', 'method', 'amount', 'reff_no']));

            $message = 'Amount Deposit successfully.';
        } else {
            $rules = array_merge($commonRules, [
                'case'    => 'required|string',
                'remarks' => 'nullable|string',
            ]);

            $validated = $request->validate($rules);

            DailyLedgerUse::create($request->only(['date', 'case', 'amount', 'remarks']));

            $message = 'Amount Use successfully.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $Request)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $Request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Request $Request)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $Request)
    {
        //
    }
}
