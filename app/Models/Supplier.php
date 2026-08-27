<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\SupplierComputed;
use App\Support\DateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\Voucher;

class Supplier extends Model
{
    use HasFactory;

    use Filterable, SupplierComputed;

    protected $hidden = [
        'user_id',
        'creator_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'user_id',
        'branch_id',
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

    public function statementAdjustments()
    {
        return $this->morphMany(StatementAdjustment::class, 'adjustable');
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

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'supplier_id');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true, ?array $branchIds = null, bool $includeNullBranchRecords = false)
    {
        $expenseQuery = $this->expenses();

        $inventoryExpenseQuery = $this->inventoryTransactions();

        $paymentsQuery = $this->payments()
            ->whereRaw('LOWER(method) IN (?, ?, ?, ?, ?, ?, ?, ?)', [
                'cheque',
                'cash',
                'slip',
                'atm',
                'self cheque',
                'program',
                'p. return',
                'adjustment',
            ]);

        $adjustmentsQuery = $this->statementAdjustments();

        $productionQuery = null;
        if ($this->worker) {
            $productionQuery = $this->worker->productions()->select([
                'id',
                'worker_id',
                'receive_date',
                'amount',
            ]);
        }
        $branchIds = collect($branchIds ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $applyBranchScope = function ($query, string $table) use ($branchIds, $includeNullBranchRecords) {
            if (!count($branchIds) || !Schema::hasColumn($table, 'branch_id')) {
                return;
            }

            $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                $nested->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $nested->orWhereNull('branch_id');
                }
            });
        };

        $applyBranchScope($expenseQuery, 'expenses');
        $applyBranchScope($inventoryExpenseQuery, 'inventory_transactions');
        $applyBranchScope($paymentsQuery, 'supplier_payments');
        $paymentsQuery->whereNotNull('voucher_id');
        $applyBranchScope($adjustmentsQuery, 'statement_adjustments');

        if ($productionQuery) {
            $applyBranchScope($productionQuery, 'productions');
        }

        DateRange::apply($expenseQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($inventoryExpenseQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($paymentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($adjustmentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);

        if ($productionQuery) {
            DateRange::apply($productionQuery, 'receive_date', $fromDate, $toDate, $includeGivenDate);
        }

        $totalExpense = $expenseQuery->sum('amount') ?? 0;

        $totalInventoryExpense = (float) $inventoryExpenseQuery->get()->sum(
            fn ($transaction) => $transaction->direction === 'out'
                ? -(float) ($transaction->amount ?? 0)
                : (float) ($transaction->amount ?? 0)
        );

        $totalPayments = $paymentsQuery->sum('amount') ?? 0;

        $totalProduction = $productionQuery ? $productionQuery->sum('amount') : 0;

        $adjustmentsNet = (float) $adjustmentsQuery
            ->get()
            ->sum(fn ($adjustment) => (float) $adjustment->net_amount);

        $balance = (
            ($totalExpense + $totalInventoryExpense + $totalProduction)
            - $totalPayments
        ) + $adjustmentsNet;

        return $formatted ? \App\Support\Money::format($balance) : $balance;
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
        $end   = Carbon::parse($toDate)->toDateString();

        $openingBalance = $this->calculateBalance(null, $fromDate, false, false, $branchIds, $includeNullBranchRecords);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate, false, true, $branchIds, $includeNullBranchRecords);
        $closingBalance = $openingBalance + $periodBalance;
        $branchScope = fn ($query) => $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
            $nested->whereIn('branch_id', $branchIds);
            if ($includeNullBranchRecords) {
                $nested->orWhereNull('branch_id');
            }
        });

        $expenseQuery = $this->expenses()
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('expenses', 'branch_id'), $branchScope);
        $inventoryExpenseQuery = $this->inventoryTransactions()
            ->whereBetween(
                \Illuminate\Support\Facades\DB::raw('DATE(date)'),
                [$start, $end]
            )
            ->when(
                $hasBranchScope && Schema::hasColumn('inventory_transactions', 'branch_id'),
                $branchScope
            );
        $paymentQuery = $this->payments()
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('supplier_payments', 'branch_id'), $branchScope)
            ->whereIn('method', [
                'Cheque', 'Cash', 'Slip', 'ATM', 'Self Cheque', 'program', 'p. return', 'Adjustment'
            ])
            ->whereNotNull('voucher_id');
        $voucherQuery = Voucher::with('payments')
            ->where('supplier_id', $this->id)
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('vouchers', 'branch_id'), $branchScope);
        $adjustmentsQuery = $this->statementAdjustments()
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$start, $end])
            ->when($hasBranchScope && Schema::hasColumn('statement_adjustments', 'branch_id'), $branchScope);
        $productionQuery = $this->worker
            ? $this->worker->productions()->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(receive_date)'), [$start, $end])
                ->when($hasBranchScope && Schema::hasColumn('productions', 'branch_id'), $branchScope)
            : null;
        $pendingPaymentTotal = (clone $paymentQuery)
            ->whereNull('voucher_id')
            ->sum('amount') ?? 0;

        $normalizeDateValue = function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (!$value) {
                return null;
            }

            return Carbon::parse(str_replace(';', ':', (string) $value));
        };

        $rawDate = fn($model, string $attribute) => $normalizeDateValue(
            $model->getRawOriginal($attribute) ?? $model->getAttribute($attribute)
        );

        $makeSortKey = fn($item) =>
            $normalizeDateValue($item['date'])?->format('Ymd') . '_' .
            (isset($item['created_at']) && $item['created_at']
                ? $normalizeDateValue($item['created_at'])?->format('YmdHis')
                : '00000000');

        $mapQuery = function ($query, callable $mapper) {
            return $query && $query->exists() ? $query->get()->map($mapper) : collect();
        };

        $paymentDescription = function ($p) use ($rawDate) {
            $description = $rawDate($p, 'cheque_date')?->format('d-M-Y')
                ?? (
                    $p->slip?->customer
                        ? trim(
                            ($p->slip->slip_date?->format('d-M-Y') ?? '') .
                            ($p->slip->customer->customer_name
                                ? ' | ' . $p->slip->customer->customer_name
                                : ''
                            ) .
                            ($p->slip->customer?->city?->short_title
                                ? ' | ' . $p->slip->customer->city->short_title
                                : ''
                            ),
                            ' |'
                        )
                        : null
                )
                ?? (
                    $p->cheque?->customer
                        ? trim(
                            ($p->cheque->cheque_date?->format('d-M-Y') ?? '') .
                            ($p->cheque->customer->customer_name
                                ? ' | ' . $p->cheque->customer->customer_name
                                : ''
                            ) .
                            ($p->cheque->customer?->city?->short_title
                                ? ' | ' . $p->cheque->customer->city->short_title
                                : ''
                            ),
                            ' |'
                        )
                        : null
                )
                ?? (
                    ($p->bankAccount?->account_title || $p->bankAccount?->bank?->short_title)
                    ? trim(
                        (
                            $p->method === 'Self Cheque'
                                ? collect(explode('|', $p->bankAccount->account_title ?? ''))->last()
                                : ($p->bankAccount->account_title ?? '')
                        )
                        .
                        ($p->bankAccount?->bank?->short_title
                            ? ' | ' . $p->bankAccount->bank->short_title
                            : ''
                        ),
                        ' |'
                    )
                    : null
                )
                ?? ($p->remarks ?? '-');

            return collect([
                $description,
                $p->voucher?->voucher_no ? 'Voucher: ' . $p->voucher->voucher_no : null,
            ])->filter(fn ($value) => filled($value))->implode(' | ');
        };
        $formatDetailedAdjustment = function ($adjustment) use ($rawDate) {
            $isPlus = $adjustment->direction === 'plus';
            $label = $adjustment->entry_type === 'opening_balance' ? 'Opening Balance' : 'Adjustment';

            return [
                'date' => $rawDate($adjustment, 'date'),
                'reff_no' => ($adjustment->entry_type === 'opening_balance' ? 'OB' : 'ADJ') . '-' . $adjustment->id,
                'type' => $isPlus ? 'invoice' : 'payment',
                'method' => $label,
                'bill' => $isPlus ? (float) $adjustment->amount : 0,
                'payment' => $isPlus ? 0 : (float) $adjustment->amount,
                'description' => $adjustment->remarks ?: $label,
                'created_at' => $adjustment->created_at,
                'source' => [
                    'type' => 'statement_adjustment',
                    'id' => $adjustment->id,
                ],
            ];
        };

        if ($type === 'summarized') {
            $expenses = $mapQuery($expenseQuery, fn($i) => [
                'type' => 'invoice',
                'date' => $rawDate($i, 'date')?->toDateString(),
                'bill' => (float) ($i->amount ?? 0),
                'payment' => 0,
                'created_at' => $i->created_at,
            ]);

            $inventoryExpenses = $mapQuery($inventoryExpenseQuery, fn($i) => [
                'type' => $i->direction === 'out' ? 'payment' : 'invoice',
                'date' => $rawDate($i, 'date')?->toDateString(),
                'bill' => $i->direction === 'out' ? 0 : (float) ($i->amount ?? 0),
                'payment' => $i->direction === 'out' ? (float) ($i->amount ?? 0) : 0,
                'created_at' => $i->created_at,
            ]);

            $payments = $mapQuery($paymentQuery, fn($p) => [
                'type' => 'payment',
                'date' => $rawDate($p, 'date')?->toDateString(),
                'bill' => 0,
                'payment' => (float) ($p->amount ?? 0),
                'created_at' => $p->created_at,
            ]);

            $productions = $mapQuery($productionQuery, fn($pr) => [
                'type' => 'invoice',
                'date' => $rawDate($pr, 'receive_date')?->toDateString(),
                'bill' => (float) ($pr->amount ?? 0),
                'payment' => 0,
                'created_at' => $pr->created_at,
                'source' => [
                    'type' => 'production',
                    'id' => $pr->id,
                ],
            ]);
            $adjustments = $mapQuery($adjustmentsQuery, fn($adjustment) => [
                'type' => $adjustment->direction === 'plus' ? 'invoice' : 'payment',
                'date' => $rawDate($adjustment, 'date')?->toDateString(),
                'bill' => $adjustment->direction === 'plus' ? (float) $adjustment->amount : 0,
                'payment' => $adjustment->direction === 'minus' ? (float) $adjustment->amount : 0,
                'created_at' => $adjustment->created_at,
            ]);

            $statement = $expenses
                ->merge($inventoryExpenses)
                ->merge($productions)
                ->merge($adjustments)
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
        } elseif ($type === 'general') {
            $expenses = $mapQuery($expenseQuery, fn($i) => [
                'date' => $rawDate($i, 'date'),
                'reff_no' => $i->reff_no,
                'type' => 'invoice',
                'method' => 'Expense',
                'bill' => (float) ($i->amount ?? 0),
                'payment' => 0,
                'description' => $i->remarks ?? '-',
                'created_at' => $i->created_at,
                'source' => [
                    'type' => 'expense',
                    'id' => $i->id,
                ],
            ]);

            $inventoryExpenses = $mapQuery($inventoryExpenseQuery, fn($i) => [
                'date' => $rawDate($i, 'date'),
                'reff_no' => $i->reference_no ?? ('INV-' . $i->id),
                'type' => $i->direction === 'out' ? 'payment' : 'invoice',
                'method' => $i->direction === 'out' ? 'Inventory Return' : 'Inventory Purchase',
                'bill' => $i->direction === 'out' ? 0 : (float) ($i->amount ?? 0),
                'payment' => $i->direction === 'out' ? (float) ($i->amount ?? 0) : 0,
                'description' => $i->remarks ?? ($i->direction === 'out' ? 'Inventory Return' : 'Inventory Purchase'),
                'created_at' => $i->created_at,
                'source' => [
                    'type' => 'inventory_transaction',
                    'id' => $i->id,
                ],
            ]);

            $payments = $mapQuery($voucherQuery, function ($v) use ($rawDate) {
                $methods = $v->payments->pluck('method')->filter()->unique()->values();
                $methodLabel = $methods->count() === 1 ? $methods->first() : ($methods->count() > 1 ? 'mixed' : null);

                return [
                    'date' => $rawDate($v, 'date'),
                    'reff_no' => $v->voucher_no,
                    'type' => 'payment',
                    'method' => $methodLabel,
                    'payment' => (float) $v->payments->sum('amount'),
                    'bill' => 0,
                    'description' => 'Voucher: ' . $v->voucher_no,
                    'created_at' => $v->created_at,
                    'source' => [
                        'type' => 'voucher',
                        'id' => $v->id,
                    ],
                ];
            });

            $productions = $mapQuery($productionQuery, fn($pr) => [
                'date' => $rawDate($pr, 'receive_date'),
                'reff_no' => $pr->ticket,
                'type' => 'invoice',
                'bill' => (float) ($pr->amount ?? 0),
                'payment' => 0,
                'created_at' => $pr->created_at,
                'source' => [
                    'type' => 'production',
                    'id' => $pr->id,
                ],
            ]);
            $adjustments = $mapQuery($adjustmentsQuery, $formatDetailedAdjustment);

            $statement = $expenses
                ->merge($inventoryExpenses)
                ->merge($payments)
                ->merge($productions)
                ->merge($adjustments)
                ->sortBy($makeSortKey)
                ->values();
        } else {
            $expenses = $mapQuery($expenseQuery, fn($i) => [
                'date' => $rawDate($i, 'date'),
                'reff_no' => $i->reff_no ?? '-',
                'type' => 'invoice',
                'method' => 'Expense',
                'bill' => (float) ($i->amount ?? 0),
                'payment' => 0,
                'description' => $i->remarks . ' (' . $i->id . ')' ?? '-',
                'created_at' => $i->created_at,
                'source' => [
                    'type' => 'expense',
                    'id' => $i->id,
                ],
            ]);

            $inventoryExpenses = $mapQuery($inventoryExpenseQuery, fn($i) => [
                'date' => $rawDate($i, 'date'),
                'reff_no' => $i->reference_no ?? ('INV-' . $i->id),
                'type' => $i->direction === 'out' ? 'payment' : 'invoice',
                'method' => $i->direction === 'out' ? 'Inventory Return' : 'Inventory Purchase',
                'bill' => $i->direction === 'out' ? 0 : (float) ($i->amount ?? 0),
                'payment' => $i->direction === 'out' ? (float) ($i->amount ?? 0) : 0,
                'description' => $i->remarks
                    ? $i->remarks . ' (' . $i->id . ')'
                    : ($i->direction === 'out' ? 'Inventory Return' : 'Inventory Purchase') . ' (' . $i->id . ')',
                'created_at' => $i->created_at,
                'source' => [
                    'type' => 'inventory_transaction',
                    'id' => $i->id,
                ],
            ]);

            $payments = $mapQuery($paymentQuery, fn($p) => [
                'date' => $rawDate($p, 'date'),
                'reff_no' => $p->cheque_no ?? $p->slip?->slip_no ?? $p->cheque?->cheque_no ?? $p->transaction_id ?? $p->reff_no ?? '-',
                'type' => 'payment',
                'method' => $p->method,
                'payment' => (float) ($p->amount ?? 0),
                'bill' => 0,
                'description' => $paymentDescription($p) ?? '-',
                'created_at' => $p->created_at,
                'source' => [
                    'type' => $p->voucher_id ? 'voucher' : 'supplier_payment',
                    'id' => $p->voucher_id ?: $p->id,
                ],
            ]);

            $productions = $mapQuery($productionQuery, fn($pr) => [
                'date' => $rawDate($pr, 'receive_date'),
                'reff_no' => $pr->ticket,
                'type' => 'invoice',
                'bill' => (float) ($pr->amount ?? 0),
                'payment' => 0,
                'created_at' => $pr->created_at,
                'source' => [
                    'type' => 'production',
                    'id' => $pr->id,
                ],
            ]);
            $adjustments = $mapQuery($adjustmentsQuery, $formatDetailedAdjustment);

            $statement = $expenses
                ->merge($inventoryExpenses)
                ->merge($payments)
                ->merge($productions)
                ->merge($adjustments)
                ->sortBy($makeSortKey)
                ->values();
        }

        $billTotal = $statement->sum('bill');
        $paymentTotal = $statement->sum('payment');
        $totals = [
            'bill' => $billTotal,
            'payment' => $paymentTotal,
            'balance' => $billTotal - $paymentTotal,
            'pending_payment' => $pendingPaymentTotal,
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
