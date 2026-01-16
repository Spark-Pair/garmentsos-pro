<?php

namespace App\Models;

use App\Traits\EmployeeComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $productionsQuery = $this->productions();
        $paymentsQuery = $this->payments();
        $salariesQuery = $this->salaries(); // 👈 new line

        // Handle different date scenarios
        if ($fromDate && $toDate) {
            if ($includeGivenDate) {
                $productionsQuery->whereBetween('date', [$fromDate, $toDate]);
                $paymentsQuery->whereBetween('date', [$fromDate, $toDate]);
                $salariesQuery->whereBetween('date', [$fromDate, $toDate]); // 👈 added
            } else {
                $productionsQuery->where('date', '>', $fromDate)->where('date', '<', $toDate);
                $paymentsQuery->where('date', '>', $fromDate)->where('date', '<', $toDate);
                $salariesQuery->where('date', '>', $fromDate)->where('date', '<', $toDate); // 👈 added
            }
        } elseif ($fromDate) {
            $operator = $includeGivenDate ? '>=' : '>';
            $productionsQuery->where('date', $operator, $fromDate);
            $paymentsQuery->where('date', $operator, $fromDate);
            $salariesQuery->where('date', $operator, $fromDate); // 👈 added
        } elseif ($toDate) {
            $operator = $includeGivenDate ? '<=' : '<';
            $productionsQuery->where('date', $operator, $toDate);
            $paymentsQuery->where('date', $operator, $toDate);
            $salariesQuery->where('date', $operator, $toDate); // 👈 added
        }

        // Calculate totals
        $totalProductions = $productionsQuery->sum('netAmount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $totalSalaries = $salariesQuery->sum('amount') ?? 0; // 👈 added

        // Final balance (production - payments - salary)
        $balance = ($totalProductions + $totalSalaries) - $totalPayments;

        return $formatted ? number_format($balance, 1, '.', ',') : $balance;
    }
}
