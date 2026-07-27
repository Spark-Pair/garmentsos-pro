<?php

namespace App\Models;

use App\Traits\EmployeeComputed;
use App\Traits\Filterable;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

        DateRange::apply($productionsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($paymentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($salariesQuery, 'date', $fromDate, $toDate, $includeGivenDate);

        // Calculate totals
        $totalProductions = $productionsQuery->sum('netAmount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $totalSalaries = $salariesQuery->sum('amount') ?? 0; // 👈 added

        // Final balance (production - payments - salary)
        $balance = ($totalProductions + $totalSalaries) - $totalPayments;

        return $formatted ? \App\Support\Money::format($balance) : $balance;
    }
}
