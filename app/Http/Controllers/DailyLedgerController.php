<?php

namespace App\Http\Controllers;

use App\Models\DailyLedgerDeposit;
use App\Models\DailyLedgerUse;
use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DailyLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {
    //     if ($request->ajax()) {
    //         $totalDeposit = DailyLedgerDeposit::orderByDesc('date')->applyFilters($request, false)->get()->map->toFormattedArray();
    //         $totalUse = DailyLedgerUse::orderByDesc('date')->applyFilters($request, false)->get()->map->toFormattedArray();

    //         $dailyLedgers = $totalDeposit
    //             ->merge($totalUse)
    //             ->sort(function ($a, $b) {

    //                 $aDate = $a['date'] ?? '1970-01-01';
    //                 $bDate = $b['date'] ?? '1970-01-01';

    //                 if ($aDate === $bDate) {
    //                     return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    //                 }

    //                 return strcmp($aDate, $bDate);
    //             })
    //             ->values();

    //         $balance = 0;

    //         $dailyLedgers = $dailyLedgers->reverse()->map(function ($row) use (&$balance) {
    //             $balance += $row['deposit'];
    //             $balance -= $row['use'];
    //             $row['balance'] = $balance;
    //             return $row;
    //         })->reverse()->values(); // reverse again to restore original order

    //         return response()->json(['data' => $dailyLedgers, 'authLayout' => 'table']);
    //     }

    //     return view('daily-ledger.index');
    // }

    public function index(Request $request)
    {
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        $branches = app(ModuleBranchService::class);

        if ($request->ajax()) {
            // Get filtered deposits
            $filteredDeposits = $branches->applyScope(DailyLedgerDeposit::orderByDesc('date'), 'daily_ledger')
                ->orderByDesc('created_at')
                ->applyFilters($request, false)
                ->get()
                ->map(function($item) {
                    return $item->toFormattedArray();
                });

            // Get filtered uses
            $filteredUses = $branches->applyScope(DailyLedgerUse::orderByDesc('date'), 'daily_ledger')
                ->orderByDesc('created_at')
                ->applyFilters($request, false)
                ->get()
                ->map(function($item) {
                    return $item->toFormattedArray();
                });

            // ✅ Use collect() to merge arrays (not Eloquent merge)
            $filteredLedgers = collect($filteredDeposits)
                ->concat($filteredUses) // concat instead of merge for arrays
                ->sortBy([
                    ['date', 'asc'],
                    ['created_at', 'asc']
                ])
                ->values();

            $scopedDeposits = fn () => $branches->applyScope(DailyLedgerDeposit::query(), 'daily_ledger');
            $scopedUses = fn () => $branches->applyScope(DailyLedgerUse::query(), 'daily_ledger');

            $openingBalance = 0;
            if ($filteredLedgers->isNotEmpty()) {
                $firstVisibleDate = $filteredLedgers
                    ->pluck('date_raw')
                    ->filter()
                    ->sort()
                    ->first();

                if ($firstVisibleDate) {
                    $beforeDeposits = $scopedDeposits()->whereDate('date', '<', $firstVisibleDate)->sum('amount');
                    $beforeUses = $scopedUses()->whereDate('date', '<', $firstVisibleDate)->sum('amount');
                    $openingBalance = $beforeDeposits - $beforeUses;
                }
            } else {
                $openingBalance = $scopedDeposits()->sum('amount') - $scopedUses()->sum('amount');
            }

            // Final sort: newest first
            $finalData = $filteredLedgers
                ->sortByDesc(function($item) {
                    return [
                        \Carbon\Carbon::createFromFormat('d-M-Y, D', $item['date'])->format('Y-m-d'),
                        $item['created_at']
                    ];
                })
                ->values();

            // Calculate running balance (reverse for oldest first)
            $runningBalance = $openingBalance;
            $ledgersWithBalance = $finalData->reverse()->map(function ($row) use (&$runningBalance) {
                $runningBalance += floatval($row['deposit']);
                $runningBalance -= floatval($row['use']);
                $row['balance'] = $runningBalance;
                return $row;
            });

            // Reverse back to newest first
            $finalData = $ledgersWithBalance->reverse()->values();

            $totalDeposit = $finalData->sum('deposit');
            $totalUse = $finalData->sum('use');
            $netChange = $totalDeposit - $totalUse;
            $closingBalance = $openingBalance + $netChange;
            $totalRecordsCount = $scopedDeposits()->count() + $scopedUses()->count();

            return response()->json([
                'data' => $finalData,
                'authLayout' => $authLayout,
                'calculations' => [
                    'opening_balance' => round($openingBalance, 2),
                    'total_deposit' => round($totalDeposit, 2),
                    'total_use' => round($totalUse, 2),
                    'balance' => round($netChange, 2),
                    'closing_balance' => round($closingBalance, 2),
                    'showing_count' => $finalData->count(),
                    'total_count' => $totalRecordsCount
                ]
            ]);
        }

        return view('daily-ledger.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $branches = app(ModuleBranchService::class);
        $totalDeposit = $branches->applyScope(DailyLedgerDeposit::query(), 'daily_ledger')->sum('amount');
        $totalUse = $branches->applyScope(DailyLedgerUse::query(), 'daily_ledger')->sum('amount');
        $balance = $totalDeposit - $totalUse;
        $method_options = $this->setupOptions('daily_ledger_method');
        $case_options = $this->setupOptions('daily_ledger_case');

        return view('daily-ledger.create', compact('balance', 'method_options', 'case_options'));
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
                'method'  => [
                    'required',
                    'string',
                    Rule::in(array_keys($this->setupOptions('daily_ledger_method'))),
                ],
                'reff_no' => 'nullable|string',
            ]);

            $validated = $request->validate($rules);

            DailyLedgerDeposit::create(
                app(ModuleBranchService::class)->assignBranchOnCreate($request->only(['date', 'method', 'amount', 'reff_no']), 'daily_ledger')
            );

            $message = 'Amount Deposit successfully.';
        } else {
            $rules = array_merge($commonRules, [
                'case'    => [
                    'required',
                    'string',
                    Rule::in(array_keys($this->setupOptions('daily_ledger_case'))),
                ],
                'remarks' => 'nullable|string',
            ]);

            $validated = $request->validate($rules);

            DailyLedgerUse::create(
                app(ModuleBranchService::class)->assignBranchOnCreate($request->only(['date', 'case', 'amount', 'remarks']), 'daily_ledger')
            );

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
    public function edit(Request $request, string $dailyLedger)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        [$ledgerEntry, $entryType] = $this->findLedgerEntryOrFail($dailyLedger, $request->query('type'));
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($ledgerEntry, 'daily_ledger');

        $branches = app(ModuleBranchService::class);
        $totalDeposit = $branches->applyScope(DailyLedgerDeposit::query(), 'daily_ledger')->sum('amount');
        $totalUse = $branches->applyScope(DailyLedgerUse::query(), 'daily_ledger')->sum('amount');
        $balance = $totalDeposit - $totalUse;
        $method_options = $this->setupOptions('daily_ledger_method');
        $case_options = $this->setupOptions('daily_ledger_case');

        return view('daily-ledger.create', compact('balance', 'method_options', 'case_options', 'ledgerEntry', 'entryType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $dailyLedger)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        [$ledgerEntry, $entryType] = $this->findLedgerEntryOrFail($dailyLedger, $request->input('ledger_type', $request->query('type')));
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($ledgerEntry, 'daily_ledger');

        $commonRules = [
            'date'   => 'required|date',
            'amount' => 'required|integer',
        ];

        if ($entryType === 'deposit') {
            $request->validate(array_merge($commonRules, [
                'method'  => [
                    'required',
                    'string',
                    Rule::in(array_keys($this->setupOptions('daily_ledger_method'))),
                ],
                'reff_no' => 'nullable|string',
            ]));

            $ledgerEntry->update($request->only(['date', 'method', 'amount', 'reff_no']));
            $message = 'Daily ledger deposit updated successfully.';
        } else {
            $request->validate(array_merge($commonRules, [
                'case'    => [
                    'required',
                    'string',
                    Rule::in(array_keys($this->setupOptions('daily_ledger_case'))),
                ],
                'remarks' => 'nullable|string',
            ]));

            $ledgerEntry->update($request->only(['date', 'case', 'amount', 'remarks']));
            $message = 'Daily ledger use updated successfully.';
        }

        return redirect()->route('daily-ledger.index')->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $dailyLedger)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        [$ledgerEntry, $entryType] = $this->findLedgerEntryOrFail($dailyLedger, $request->input('ledger_type', $request->query('type')));
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($ledgerEntry, 'daily_ledger');

        $ledgerEntry->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Daily ledger record deleted successfully.',
            ]);
        }

        return redirect()->route('daily-ledger.index')->with('success', 'Daily ledger record deleted successfully.');
    }

    private function setupOptions(string $type): array
    {
        return app(ModuleBranchService::class)
            ->applyRelatedScope(Setup::where('type', $type)->orderBy('title'), 'setups', 'daily_ledger')
            ->get()
            ->mapWithKeys(fn (Setup $setup) => [
                $setup->title => ['text' => $setup->title],
            ])
            ->toArray();
    }

    private function findLedgerEntryOrFail(string|int $id, ?string $type): array
    {
        $type = strtolower((string) $type);
        abort_unless(in_array($type, ['deposit', 'use'], true), 404);

        $entry = $type === 'deposit'
            ? DailyLedgerDeposit::query()->findOrFail($id)
            : DailyLedgerUse::query()->findOrFail($id);

        return [$entry, $type];
    }
}
