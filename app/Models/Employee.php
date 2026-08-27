<?php

namespace App\Models;

use App\Traits\EmployeeComputed;
use App\Traits\Filterable;
use App\Support\DateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    use HasFactory;

    use Filterable, EmployeeComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "category",
        "branch_id",
        "type_id",
        "employee_name",
        "urdu_title",
        "phone_number",
        "joining_date",
        "cnic_no",
        "salary",
        'status',
        'profile_picture',
    ];

    protected $casts = [
        'joining_date' => 'date',
    ];

    protected $appends = ['balance'];

    public function type() {
        return $this->belongsTo(Setup::class, 'type_id');
    }

    public function tags() {
        return $this->hasMany(IssuedFabric::class, 'worker_id');
    }

    public function productions() {
        return $this->hasMany(Production::class, 'worker_id');
    }

    public function salaries() {
        return $this->hasMany(Salary::class, 'employee_id');
    }

    public function attendance() {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function payments() {
        return $this->hasMany(EmployeePayment::class, 'employee_id');
    }

    public function supplier() {
        return $this->hasOne(Supplier::class, 'worker_id');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true, ?array $branchIds = null, bool $includeNullBranchRecords = false)
    {
        $productionsQuery = $this->productions();
        $paymentsQuery = $this->payments();
        $salariesQuery = $this->salaries(); // 👈 new line
        $branchIds = collect($branchIds ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $applyBranchScope = function ($query, string $table) use ($branchIds, $includeNullBranchRecords) {
            if ($branchIds === [] || !Schema::hasColumn($table, 'branch_id')) {
                return;
            }

            $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                $nested->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $nested->orWhereNull('branch_id');
                }
            });
        };

        $applyBranchScope($productionsQuery, 'productions');
        $applyBranchScope($paymentsQuery, 'employee_payments');
        $applyBranchScope($salariesQuery, 'salaries');

        DateRange::apply($productionsQuery, 'receive_date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($paymentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        $this->applySalaryMonthRange($salariesQuery, $fromDate, $toDate, $includeGivenDate);

        // Calculate totals
        $totalProductions = $productionsQuery->sum('amount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $totalSalaries = $salariesQuery->sum('amount') ?? 0; // 👈 added

        // Final balance (production - payments - salary)
        $balance = ($totalProductions + $totalSalaries) - $totalPayments;

        return $formatted ? \App\Support\Money::format($balance) : $balance;
    }

    private function applySalaryMonthRange($query, $fromDate = null, $toDate = null, bool $includeGivenDate = true): void
    {
        if ($fromDate) {
            $fromMonth = Carbon::parse($fromDate)->format('Y-m');
            $includeGivenDate
                ? $query->where('month', '>=', $fromMonth)
                : $query->where('month', '>', $fromMonth);
        }

        if ($toDate) {
            $toMonth = Carbon::parse($toDate)->format('Y-m');
            $query->where('month', $includeGivenDate ? '<=' : '<', $toMonth);
        }
    }

    public function getStatement($fromDate, $toDate, $type = 'general', ?array $branchIds = null, bool $includeNullBranchRecords = false)
    {
        $type = $type ?: 'general';
        $branchIds = collect($branchIds ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $hasBranchScope = count($branchIds) > 0;
        $start = Carbon::parse($fromDate)->toDateString();
        $end = Carbon::parse($toDate)->toDateString();

        $openingBalance = $this->calculateBalance(null, $fromDate, false, false, $branchIds, $includeNullBranchRecords);
        $periodBalance = $this->calculateBalance($fromDate, $toDate, false, true, $branchIds, $includeNullBranchRecords);
        $closingBalance = $openingBalance + $periodBalance;
        $branchScope = fn ($query) => $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
            $nested->whereIn('branch_id', $branchIds);
            if ($includeNullBranchRecords) {
                $nested->orWhereNull('branch_id');
            }
        });

        $productionQuery = $this->productions()
            ->whereBetween(DB::raw('DATE(receive_date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('productions', 'branch_id'), $branchScope);
        $paymentQuery = $this->payments()
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('employee_payments', 'branch_id'), $branchScope);
        $salaryQuery = $this->salaries()
            ->whereBetween('month', [Carbon::parse($fromDate)->format('Y-m'), Carbon::parse($toDate)->format('Y-m')])
            ->when($hasBranchScope && Schema::hasColumn('salaries', 'branch_id'), $branchScope);

        $normalizeDateValue = function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            return $value ? Carbon::parse(str_replace(';', ':', (string) $value)) : null;
        };
        $rawDate = fn ($model, string $attribute) => $normalizeDateValue(
            $model->getRawOriginal($attribute) ?? $model->getAttribute($attribute)
        );
        $salaryDate = fn ($salary) => Carbon::parse($salary->month . '-01');
        $makeSortKey = fn ($item) =>
            $normalizeDateValue($item['date'])?->format('Ymd') . '_' .
            (isset($item['created_at']) && $item['created_at']
                ? $normalizeDateValue($item['created_at'])?->format('YmdHis')
                : '00000000');
        $mapQuery = function ($query, callable $mapper) {
            return $query && $query->exists() ? $query->get()->map($mapper) : collect();
        };

        if ($type === 'summarized') {
            $productions = $mapQuery($productionQuery, fn ($production) => [
                'type' => 'invoice',
                'date' => $rawDate($production, 'receive_date')?->toDateString(),
                'bill' => (float) ($production->amount ?? 0),
                'payment' => 0,
                'created_at' => $production->created_at,
                'source' => [
                    'type' => 'production',
                    'id' => $production->id,
                ],
            ]);
            $salaries = $mapQuery($salaryQuery, fn ($salary) => [
                'type' => 'invoice',
                'date' => $salaryDate($salary)->toDateString(),
                'bill' => (float) ($salary->amount ?? 0),
                'payment' => 0,
                'created_at' => $salary->created_at,
                'source' => [
                    'type' => 'salary',
                    'id' => $salary->id,
                ],
            ]);
            $payments = $mapQuery($paymentQuery, fn ($payment) => [
                'type' => 'payment',
                'date' => $rawDate($payment, 'date')?->toDateString(),
                'bill' => 0,
                'payment' => (float) ($payment->amount ?? 0),
                'created_at' => $payment->created_at,
            ]);

            $statement = $productions
                ->merge($salaries)
                ->merge($payments)
                ->groupBy('date')
                ->flatMap(function ($rows, $date) {
                    $rows = $rows->sortBy('created_at');
                    $billSum = $rows->sum('bill');
                    $paymentSum = $rows->sum('payment');
                    $result = collect();

                    if ($paymentSum > 0) {
                        $result->push([
                            'type' => 'payment',
                            'date' => Carbon::parse(str_replace(';', ':', (string) $date)),
                            'bill' => 0,
                            'payment' => $paymentSum,
                            'created_at' => $rows->where('type', 'payment')->min('created_at'),
                        ]);
                    }

                    if ($billSum > 0) {
                        $result->push([
                            'type' => 'invoice',
                            'date' => Carbon::parse(str_replace(';', ':', (string) $date)),
                            'bill' => $billSum,
                            'payment' => 0,
                            'created_at' => $rows->where('type', 'invoice')->min('created_at'),
                        ]);
                    }

                    return $result->sortBy('created_at')->values();
                })
                ->sortBy($makeSortKey)
                ->values();
        } else {
            $productions = $mapQuery($productionQuery, fn ($production) => [
                'date' => $rawDate($production, 'receive_date'),
                'reff_no' => $production->ticket ?? '-',
                'type' => 'invoice',
                'method' => 'Production',
                'bill' => (float) ($production->amount ?? 0),
                'payment' => 0,
                'description' => $production->title ?? '-',
                'created_at' => $production->created_at,
                'source' => [
                    'type' => 'production',
                    'id' => $production->id,
                ],
            ]);
            $salaries = $mapQuery($salaryQuery, fn ($salary) => [
                'date' => $salaryDate($salary),
                'reff_no' => 'SAL-' . $salary->id,
                'type' => 'invoice',
                'method' => 'Salary',
                'bill' => (float) ($salary->amount ?? 0),
                'payment' => 0,
                'description' => Carbon::parse($salary->month . '-01')->format('M Y'),
                'created_at' => $salary->created_at,
                'source' => [
                    'type' => 'salary',
                    'id' => $salary->id,
                ],
            ]);
            $payments = $mapQuery($paymentQuery, fn ($payment) => [
                'date' => $rawDate($payment, 'date'),
                'reff_no' => 'EP-' . $payment->id,
                'type' => 'payment',
                'method' => $payment->method,
                'payment' => (float) ($payment->amount ?? 0),
                'bill' => 0,
                'description' => '-',
                'created_at' => $payment->created_at,
                'source' => [
                    'type' => 'employee_payment',
                    'id' => $payment->id,
                ],
            ]);

            $statement = $productions
                ->merge($salaries)
                ->merge($payments)
                ->sortBy($makeSortKey)
                ->values();
        }

        $billTotal = $statement->sum('bill');
        $paymentTotal = $statement->sum('payment');

        return [
            'date' => Carbon::parse($fromDate)->format('d-M-Y') . ' - ' . Carbon::parse($toDate)->format('d-M-Y'),
            'name' => $this->employee_name,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => [
                'bill' => $billTotal,
                'payment' => $paymentTotal,
                'balance' => $billTotal - $paymentTotal,
                'pending_payment' => 0,
            ],
            'category' => 'employee',
            'mode' => $type,
        ];
    }
}
