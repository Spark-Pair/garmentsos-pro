<?php

namespace App\Models;

use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Supplier extends Model
{
    use HasFactory;

    use Filterable;

    protected $hidden = [
        'user_id',
        'creator_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'user_id',
        'supplier_name',
        'person_name',
        'urdu_title',
        'phone_number',
        'date',
        'categories_array',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['balance', 'categories'];

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });

        // Always eager load the associated creator
        static::addGlobalScope('withCreator', function (Builder $builder) {
            $builder->with('creator');
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentPrograms()
    {
        return $this->morphMany(PaymentProgram::class, 'sub_category');
    }

    public function bankAccounts()
    {
        return $this->morphMany(BankAccount::class, 'sub_category');
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }

    public function getCategoriesAttribute() {
        $ids = json_decode($this->categories_array, true);
        return is_array($ids) ? Setup::whereIn('id', $ids)->get() : [];
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function worker()
    {
        return $this->belongsTo(Employee::class, 'worker_id');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $expenseQuery = $this->expenses();
        $paymentsQuery = $this->payments()
            ->whereNotNull('voucher_id')
            ->whereIn('method', [
                'Cheque',
                'Cash',
                'Slip',
                'ATM',
                'Self Cheque',
                'Program',
                'Adjustment',
            ]);

        $productionQuery = null;
        if ($this->worker) {
            $productionQuery = $this->worker->productions()->select([
                'id',
                'worker_id',
                'receive_date',
                'amount',
            ]);
        }

        // Inclusive date handling
        if ($fromDate) $fromDate = Carbon::parse($fromDate)->startOfDay();
        if ($toDate) $toDate = Carbon::parse($toDate)->endOfDay();

        if ($fromDate && $toDate) {
            $expenseQuery->whereBetween('date', [$fromDate, $toDate]);
            $paymentsQuery->whereBetween('date', [$fromDate, $toDate]);
            if ($productionQuery) $productionQuery->whereBetween('receive_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $expenseQuery->where('date', '>=', $fromDate);
            $paymentsQuery->where('date', '>=', $fromDate);
            if ($productionQuery) $productionQuery->where('receive_date', '>=', $fromDate);
        } elseif ($toDate) {
            $expenseQuery->where('date', '<=', $toDate);
            $paymentsQuery->where('date', '<=', $toDate);
            if ($productionQuery) $productionQuery->where('receive_date', '<=', $toDate);
        }

        $totalExpense = $expenseQuery->sum('amount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $totalProduction = $productionQuery ? $productionQuery->sum('amount') : 0;

        $balance = ($totalExpense + $totalProduction) - $totalPayments;

        return $formatted ? number_format($balance, 1, '.', ',') : $balance;
    }


    public function getStatement($fromDate, $toDate, $type = 'summarized')
    {
        $start = Carbon::parse($fromDate)->startOfDay();
        $end   = Carbon::parse($toDate)->endOfDay();

        $openingBalance = $this->calculateBalance(null, $fromDate, false, false);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate, false, true);
        $closingBalance = $openingBalance + $periodBalance;

        $expenseQuery = $this->expenses()->whereBetween('date', [$start, $end]);
        $paymentQuery = $this->payments()
            ->whereBetween('date', [$start, $end])
            ->whereIn('method', [
                'Cheque', 'Cash', 'Slip', 'ATM', 'Self Cheque', 'program', 'Adjustment'
            ]);
        $productionQuery = $this->worker
            ? $this->worker->productions()->whereBetween('receive_date', [$start, $end])
            : null;

        $makeSortKey = fn($item) =>
            Carbon::parse($item['date'])->format('Ymd') . '_' .
            (isset($item['created_at']) && $item['created_at']
                ? Carbon::parse($item['created_at'])->format('YmdHis')
                : '00000000');

        $mapQuery = function ($query, callable $mapper) {
            return $query && $query->exists() ? $query->get()->map($mapper) : collect();
        };

        if ($type === 'summarized') {
            $expenses = $mapQuery($expenseQuery, fn($i) => [
                'type' => 'invoice',
                'date' => Carbon::parse($i->date)->toDateString(),
                'bill' => (float) ($i->amount ?? 0),
                'payment' => 0,
                'created_at' => $i->created_at,
            ]);

            $payments = $mapQuery($paymentQuery, fn($p) => [
                'type' => 'payment',
                'date' => Carbon::parse($p->date)->toDateString(),
                'bill' => 0,
                'payment' => (float) ($p->amount ?? 0),
                'created_at' => $p->created_at,
            ]);

            $productions = $mapQuery($productionQuery, fn($pr) => [
                'type' => 'invoice',
                'date' => Carbon::parse($pr->receive_date)->toDateString(),
                'bill' => (float) ($pr->amount ?? 0),
                'payment' => 0,
                'created_at' => $pr->created_at,
            ]);

            $statement = $expenses
                ->merge($productions)
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
                            'date' => Carbon::parse($date),
                            'bill' => 0,
                            'payment' => $paymentSum,
                            'created_at' => $rows->where('type', 'payment')->min('created_at'),
                        ]);
                    }

                    if ($billSum > 0) {
                        $result->push([
                            'type' => 'invoice',
                            'date' => Carbon::parse($date),
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
            $expenses = $mapQuery($expenseQuery, fn($i) => [
                'date' => $i->date,
                'reff_no' => $i->reff_no,
                'type' => 'invoice',
                'bill' => (float) ($i->amount ?? 0),
                'payment' => 0,
                'created_at' => $i->created_at,
            ]);

            $payments = $mapQuery($paymentQuery->with('bankAccount.bank'), fn($p) => [
                'date' => $p->date,
                'reff_no' => $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no,
                'type' => 'payment',
                'method' => $p->method,
                'payment' => (float) ($p->amount ?? 0),
                'bill' => 0,
                'description' =>
                    $p->cheque_date?->format('d-M-Y, D')
                    ?? $p->slip_date?->format('d-M-Y, D')
                    ?? (($p->bankAccount?->account_title || $p->bankAccount?->bank?->short_title)
                        ? trim(
                            ($p->bankAccount?->account_title ?? '') .
                            ($p->bankAccount?->bank?->short_title ? ' | ' . $p->bankAccount->bank->short_title : ''),
                            ' |'
                        )
                        : null),
                'created_at' => $p->created_at,
            ]);

            $productions = $mapQuery($productionQuery, fn($pr) => [
                'date' => $pr->receive_date,
                'reff_no' => $pr->ticket,
                'type' => 'invoice',
                'bill' => (float) ($pr->amount ?? 0),
                'payment' => 0,
                'created_at' => $pr->created_at,
            ]);

            $statement = $expenses
                ->merge($payments)
                ->merge($productions)
                ->sortBy($makeSortKey)
                ->values();
        }

        $billTotal = $statement->sum('bill');
        $paymentTotal = $statement->sum('payment');
        $totals = [
            'bill' => $billTotal,
            'payment' => $paymentTotal,
            'balance' => $billTotal - $paymentTotal,
        ];

        return [
            'date' => Carbon::parse($fromDate)->format('d-M-Y') . ' - ' . Carbon::parse($toDate)->format('d-M-Y'),
            'name' => $this->supplier_name,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => $totals,
            'category' => 'supplier',
            'mode' => $type,
        ];
    }
}
