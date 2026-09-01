<?php

namespace App\Models;

use App\Traits\BankAccountComputed;
use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankAccount extends Model
{
    use HasFactory;
    use Filterable, BankAccountComputed;

    protected $fillable = [
        'category', 'sub_category', 'bank_id', 'account_title',
        'date', 'remarks', 'account_no', 'chqbk_serial_start',
        'chqbk_serial_end', 'status', 'branch_id'
    ];

    protected $hidden = [
        'bank_id', 'creator_id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['balance', 'available_cheques'];

    // ─────────────────────────────────────────
    // Booted
    // ─────────────────────────────────────────

    protected static function booted()
    {
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });

        static::addGlobalScope('withCreator', function (Builder $builder) {
            $builder->with('creator');
        });
    }

    // ─────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function subCategory()
    {
        return $this->morphTo();
    }

    public function bank()
    {
        return $this->belongsTo(Setup::class, 'bank_id')->where('type', 'bank_name');
    }

    public function paymentPrograms()
    {
        return $this->morphMany(PaymentProgram::class, 'sub_category');
    }

    public function bankAccounts()
    {
        return $this->hasOne(BankAccount::class, 'id');
    }

    public function statementAdjustments()
    {
        return $this->morphMany(StatementAdjustment::class, 'adjustable');
    }

    // ─────────────────────────────────────────
    // Attributes
    // ─────────────────────────────────────────

    public function getAvailableChequesAttribute()
    {
        if ($this->category !== 'self') return null;

        $start = (int) $this->chqbk_serial_start;
        $end   = (int) $this->chqbk_serial_end;

        if ($start <= 0 || $end <= 0 || $end < $start) return [];

        $usedCheques = SupplierPayment::where('bank_account_id', $this->id)
            ->with('cheque:id,cheque_no')
            ->get(['id', 'cheque_id', 'cheque_no'])
            ->map(fn ($payment) => $payment->cheque_no ?: $payment->cheque?->cheque_no)
            ->filter(fn ($chequeNo) => filled($chequeNo))
            ->map(fn ($chequeNo) => (int) $chequeNo)
            ->unique()
            ->values()
            ->all();

        return array_values(array_diff(range($start, $end), $usedCheques));
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    // ─────────────────────────────────────────
    // calculateBalance
    // ─────────────────────────────────────────

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true, ?array $branchIds = null, bool $includeNullBranchRecords = false)
    {
        $balance = 0;
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

        if ($this->category === 'self') {
            // Normal CustomerPayments (no cheque, no slip) — filter by own date
            $normalPayments = CustomerPayment::where('bank_account_id', $this->id)
                ->where('is_return', false)
                ->whereNull('cheque_no')
                ->whereNull('slip_no');
            $applyBranchScope($normalPayments, 'customer_payments');
            $this->applyDateFilter($normalPayments, 'date', $fromDate, $toDate, $includeGivenDate);

            // Cheque CustomerPayments — filter by voucher date
            $chequePayments = CustomerPayment::where('bank_account_id', $this->id)
                ->where('is_return', false)
                ->whereNotNull('cheque_no')
                ->whereHas('cheque.voucher', function ($q) use ($fromDate, $toDate, $includeGivenDate) {
                    $this->applyDateFilter($q, 'date', $fromDate, $toDate, $includeGivenDate);
                });
            $applyBranchScope($chequePayments, 'customer_payments');

            // Slip CustomerPayments — filter by voucher date
            $slipPayments = CustomerPayment::where('bank_account_id', $this->id)
                ->where('is_return', false)
                ->whereNotNull('slip_no')
                ->whereHas('slip.voucher', function ($q) use ($fromDate, $toDate, $includeGivenDate) {
                    $this->applyDateFilter($q, 'date', $fromDate, $toDate, $includeGivenDate);
                });
            $applyBranchScope($slipPayments, 'customer_payments');

            // SupplierPayments — filter by own date
            $supplierPayments = SupplierPayment::where('bank_account_id', $this->id)
                ->where(function ($query) {
                    $query->whereNotNull('voucher_id')
                        ->orWhereNotNull('c_r_id');
                })
                ->where('is_return', false);
            $applyBranchScope($supplierPayments, 'supplier_payments');
            $this->applyDateFilter($supplierPayments, 'date', $fromDate, $toDate, $includeGivenDate);

            // Older self transfers can have the source withdrawal and voucher but
            // no linked CustomerPayment for the destination account.
            $unpairedTransferInflows = SupplierPayment::where('self_account_id', $this->id)
                ->whereIn('method', ['Self Cheque', 'ATM'])
                ->where('is_return', false)
                ->where(function ($query) {
                    $query->whereNull('cheque_id')
                        ->orWhereDoesntHave('cheque');
                });
            $applyBranchScope($unpairedTransferInflows, 'supplier_payments');
            $this->applyDateFilter($unpairedTransferInflows, 'date', $fromDate, $toDate, $includeGivenDate);

            // Adjustments
            $adjustmentsQuery = $this->statementAdjustments();
            $applyBranchScope($adjustmentsQuery, 'statement_adjustments');
            $this->applyDateFilter($adjustmentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);

            $totalInflow  = $normalPayments->sum('amount')
                        + $chequePayments->sum('amount')
                        + $slipPayments->sum('amount')
                        + $unpairedTransferInflows->sum('amount');

            $totalOutflow = $supplierPayments->sum('amount');

            $adjustmentsNet = (float) $adjustmentsQuery->get()
                ->sum(fn($adj) => (float) $adj->net_amount);

            $balance = ($totalInflow - $totalOutflow) + $adjustmentsNet;

        } elseif (in_array($this->category, ['supplier', 'customer'])) {

            $clearBalance = PaymentClear::where('bank_account_id', $this->id)
                ->where('method', '!=', 'cash');
            $applyBranchScope($clearBalance, 'payment_clears');
            $this->applyDateFilter($clearBalance, 'clear_date', $fromDate, $toDate, $includeGivenDate);

            $adjustmentsQuery = $this->statementAdjustments();
            $applyBranchScope($adjustmentsQuery, 'statement_adjustments');
            $this->applyDateFilter($adjustmentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);

            $adjustmentsNet = (float) $adjustmentsQuery->get()
                ->sum(fn($adj) => (float) $adj->net_amount);

            $balance = $clearBalance->sum('amount') + $adjustmentsNet;
        }

        return $formatted ? \App\Support\Money::format($balance) : $balance;
    }

    // ─────────────────────────────────────────
    // getStatement
    // ─────────────────────────────────────────

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

        $from = Carbon::parse($fromDate)->toDateString();
        $to   = Carbon::parse($toDate)->toDateString();

        // Opening & Closing Balances
        $openingBalance = $this->calculateBalance(null, $fromDate, false, false, $branchIds, $includeNullBranchRecords);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate, false, true, $branchIds, $includeNullBranchRecords);
        $closingBalance = $openingBalance + $periodBalance;
        $branchScope = fn ($query) => $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
            $nested->whereIn('branch_id', $branchIds);
            if ($includeNullBranchRecords) {
                $nested->orWhereNull('branch_id');
            }
        });

        // ── CustomerPayments query ── (aligned with calculateBalance logic)
        $customerQuery = CustomerPayment::where('bank_account_id', $this->id)
            ->when($this->category === 'self', fn ($query) => $query->where('is_return', false))
            ->when($hasBranchScope && Schema::hasColumn('customer_payments', 'branch_id'), $branchScope)
            ->where(function ($q) use ($from, $to) {
                // Normal (no cheque, no slip) — filter by own date
                $q->where(function ($q) use ($from, $to) {
                    $q->whereNull('cheque_no')
                    ->whereNull('slip_no')
                    ->whereBetween(DB::raw('DATE(date)'), [$from, $to]);
                })
                // Cheque — filter by voucher date
                ->orWhere(function ($q) use ($from, $to) {
                    $q->whereNotNull('cheque_no')
                    ->whereHas('cheque.voucher', fn($q) =>
                        $q->whereBetween(DB::raw('DATE(date)'), [$from, $to])
                    );
                })
                // Slip — filter by voucher date
                ->orWhere(function ($q) use ($from, $to) {
                    $q->whereNotNull('slip_no')
                    ->whereHas('slip.voucher', fn($q) =>
                        $q->whereBetween(DB::raw('DATE(date)'), [$from, $to])
                    );
                });
            })
            ->with([
                'cheque.voucher',
                'cheque.bankAccount.bank',
                'cheque.selfAccount.bank',
                'slip.voucher',
                'customer.city',
            ]);

        // ── SupplierPayments query ──
        $supplierQuery = SupplierPayment::where('bank_account_id', $this->id)
            ->when($this->category === 'self', fn ($query) => $query->where('is_return', false))
            ->whereBetween(DB::raw('DATE(date)'), [$from, $to])
            ->when($hasBranchScope && Schema::hasColumn('supplier_payments', 'branch_id'), $branchScope)
            ->with('bankAccount.bank', 'selfAccount.bank', 'voucher', 'cheque', 'slip');
        $pendingPaymentTotal = (clone $supplierQuery)
            ->whereNull('voucher_id')
            ->whereNull('c_r_id')
            ->sum('amount') ?? 0;
        $supplierQuery->where(function ($query) {
            $query->whereNotNull('voucher_id')
                ->orWhereNotNull('c_r_id');
        });

        $unpairedTransferQuery = SupplierPayment::where('self_account_id', $this->id)
            ->whereIn('method', ['Self Cheque', 'ATM'])
            ->where('is_return', false)
            ->where(function ($query) {
                $query->whereNull('cheque_id')
                    ->orWhereDoesntHave('cheque');
            })
            ->whereBetween(DB::raw('DATE(date)'), [$from, $to])
            ->when($hasBranchScope && Schema::hasColumn('supplier_payments', 'branch_id'), $branchScope)
            ->with('bankAccount.bank', 'selfAccount.bank', 'voucher');

        // ── Adjustments ──
        $adjustments = $this->statementAdjustments()
            ->whereBetween(DB::raw('DATE(date)'), [$from, $to])
            ->when($hasBranchScope && Schema::hasColumn('statement_adjustments', 'branch_id'), $branchScope)
            ->get();

        $formatAdjustment = function ($adjustment, $forSummary = false) {
            $isPlus = $adjustment->direction === 'plus';
            $label  = $adjustment->entry_type === 'opening_balance' ? 'Opening Balance' : 'Adjustment';
            return [
                'date'        => $forSummary
                    ? Carbon::parse($adjustment->date)->toDateString()
                    : $adjustment->date,
                'reff_no'     => ($adjustment->entry_type === 'opening_balance' ? 'OB' : 'ADJ') . '-' . $adjustment->id,
                'type'        => $isPlus ? 'invoice' : 'payment',
                'method'      => $label,
                'bill'        => $isPlus ? (float) $adjustment->amount : 0,
                'payment'     => $isPlus ? 0 : (float) $adjustment->amount,
                'description' => $adjustment->remarks ?: $label,
                'created_at'  => $adjustment->created_at,
                'source'      => [
                    'type' => 'statement_adjustment',
                    'id'   => $adjustment->id,
                ],
            ];
        };

        // Helper: effective date for a CustomerPayment
        $effectiveDate = fn($p) => $p->cheque_no
            ? ($p->cheque?->voucher?->date ?? $p->date)
            : ($p->slip_no
                ? ($p->slip?->voucher?->date ?? $p->date)
                : $p->date);

        $customerPaymentDescription = function ($payment): string {
            $linkedTransfer = $payment->type === 'self_account_deposit'
                ? ($payment->cheque ?: $payment->slip)
                : null;

            if ($linkedTransfer?->bankAccount && $linkedTransfer?->selfAccount) {
                return $this->supplierPaymentStatementDescription($linkedTransfer);
            }

            $party = collect([
                $payment->customer?->customer_name,
                $payment->customer?->city?->short_title,
            ])->filter(fn ($value) => filled($value))->implode(' | ');

            if ($party !== '') {
                return $party;
            }

            if (filled($payment->remarks)) {
                return (string) $payment->remarks;
            }

            return $payment->type === 'self_account_deposit'
                ? 'Self Account Deposit'
                : 'Customer Payment';
        };

        $customerPaymentSource = function ($payment): array {
            $linkedTransfer = $payment->type === 'self_account_deposit'
                ? ($payment->cheque ?: $payment->slip)
                : null;

            if ($linkedTransfer?->voucher_id) {
                return [
                    'type' => 'voucher',
                    'id' => $linkedTransfer->voucher_id,
                ];
            }

            return [
                'type' => 'customer_payment',
                'id' => $payment->id,
            ];
        };

        $customerPaymentReference = function ($payment) {
            $linkedTransfer = $payment->type === 'self_account_deposit'
                ? ($payment->cheque ?: $payment->slip)
                : null;

            return $payment->cheque_no
                ?? $payment->slip_no
                ?? $payment->transaction_id
                ?? $payment->reff_no;
        };

        // ─────────────────────────────────────
        // SUMMARIZED
        // ─────────────────────────────────────
        if ($type === 'summarized') {

            $customerPayments = collect($customerQuery->get())->map(fn($p) => [
                'type'       => 'invoice',
                'date'       => Carbon::parse($effectiveDate($p))->toDateString(),
                'bill'       => (float) ($p->amount ?? 0),
                'payment'    => 0,
                'created_at' => $p->created_at,
            ]);

            $supplierPayments = collect($supplierQuery->get())->map(fn($p) => [
                'type'       => 'payment',
                'date'       => Carbon::parse($p->date)->toDateString(),
                'bill'       => 0,
                'payment'    => (float) ($p->amount ?? 0),
                'created_at' => $p->created_at,
            ]);

            $unpairedTransferPayments = collect($unpairedTransferQuery->get())->map(fn($p) => [
                'type'       => 'invoice',
                'date'       => Carbon::parse($p->date)->toDateString(),
                'bill'       => (float) ($p->amount ?? 0),
                'payment'    => 0,
                'created_at' => $p->created_at,
            ]);

            $adjustmentRows = $adjustments->map(fn($adj) => $formatAdjustment($adj, true));

            $statement = $customerPayments
                ->merge($supplierPayments)
                ->merge($unpairedTransferPayments)
                ->merge($adjustmentRows)
                ->groupBy('date')
                ->flatMap(function ($rows, $date) {
                    $rows       = $rows->sortBy('created_at');
                    $billSum    = (float) $rows->sum('bill');
                    $paymentSum = (float) $rows->sum('payment');
                    $results    = [];

                    if ($paymentSum > 0) {
                        $results[] = [
                            'type'       => 'payment',
                            'date'       => Carbon::parse($date),
                            'bill'       => 0,
                            'payment'    => $paymentSum,
                            'created_at' => $rows->where('type', 'payment')->min('created_at'),
                        ];
                    }

                    if ($billSum > 0) {
                        $results[] = [
                            'type'       => 'invoice',
                            'date'       => Carbon::parse($date),
                            'bill'       => $billSum,
                            'payment'    => 0,
                            'created_at' => $rows->where('type', 'invoice')->min('created_at'),
                        ];
                    }

                    return collect($results)->sortBy('created_at')->values();
                })
                ->sortBy([['date', 'asc'], ['created_at', 'asc']])
                ->values();

        // ─────────────────────────────────────
        // GENERAL (detailed)
        // ─────────────────────────────────────
        } else {

            $customerPayments = collect($customerQuery->get())->map(fn($p) => [
                'date'        => $effectiveDate($p),
                'reff_no'     => $customerPaymentReference($p),
                'type'        => 'invoice',
                'method'      => $p->method ?? null,
                'bill'        => (float) ($p->amount ?? 0),
                'payment'     => 0,
                'description' => $customerPaymentDescription($p),
                'created_at'  => $p->created_at ?? null,
                'source'      => $customerPaymentSource($p),
            ]);

            $supplierPayments = collect($supplierQuery->get())->map(fn($p) => [
                'date'        => $p->date ?? null,
                'reff_no'     => $this->supplierPaymentStatementReference($p),
                'type'        => 'payment',
                'method'      => $p->method ?? null,
                'payment'     => (float) ($p->amount ?? 0),
                'bill'        => 0,
                'description' => $this->supplierPaymentStatementDescription($p),
                'created_at'  => $p->created_at ?? null,
                'source'      => [
                    'type' => $p->voucher_id ? 'voucher' : 'supplier_payment',
                    'id'   => $p->voucher_id ?: $p->id,
                ],
            ]);

            $unpairedTransferPayments = collect($unpairedTransferQuery->get())->map(fn($p) => [
                'date'        => $p->date,
                'reff_no'     => $this->supplierPaymentStatementReference($p),
                'type'        => 'invoice',
                'method'      => $p->method,
                'payment'     => 0,
                'bill'        => (float) ($p->amount ?? 0),
                'description' => $this->supplierPaymentStatementDescription($p),
                'created_at'  => $p->created_at,
                'source'      => [
                    'type' => $p->voucher_id ? 'voucher' : 'supplier_payment',
                    'id'   => $p->voucher_id ?: $p->id,
                ],
            ]);

            $adjustmentRows = $adjustments->map(fn($adj) => $formatAdjustment($adj));

            $statement = $customerPayments
                ->merge($supplierPayments)
                ->merge($unpairedTransferPayments)
                ->merge($adjustmentRows)
                ->sortBy([['date', 'asc'], ['created_at', 'asc']])
                ->values();
        }

        $billTotal    = $statement->sum('bill');
        $paymentTotal = $statement->sum('payment');

        return [
            'date'            => Carbon::parse($fromDate)->format('d-M-Y') . ' - ' . Carbon::parse($toDate)->format('d-M-Y'),
            'name'            => "{$this->account_title} | {$this->bank->short_title}",
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements'      => $statement,
            'totals'          => [
                'bill'    => $billTotal,
                'payment' => $paymentTotal,
                'balance' => $billTotal - $paymentTotal,
                'pending_payment' => $pendingPaymentTotal,
            ],
            'category' => 'account',
        ];
    }

    // ─────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────

    private function applyDateFilter($query, string $column, $fromDate, $toDate, bool $includeGivenDate): void
    {
        $from = $fromDate ? Carbon::parse($fromDate)->toDateString() : null;
        $to   = $toDate   ? Carbon::parse($toDate)->toDateString()   : null;

        if ($from && $to) {
            if ($includeGivenDate) {
                $query->whereBetween(DB::raw("DATE($column)"), [$from, $to]);
            } else {
                $query->where(DB::raw("DATE($column)"), '>', $from)
                      ->where(DB::raw("DATE($column)"), '<', $to);
            }
        } elseif ($from) {
            $query->where(DB::raw("DATE($column)"), $includeGivenDate ? '>=' : '>', $from);
        } elseif ($to) {
            $query->where(DB::raw("DATE($column)"), $includeGivenDate ? '<=' : '<', $to);
        }
    }

    private function supplierPaymentStatementDescription(SupplierPayment $payment): string
    {
        if ($payment->bankAccount && $payment->selfAccount) {
            $from = $this->statementAccountLabel($payment->bankAccount);
            $to = $this->statementAccountLabel($payment->selfAccount);
            $details = collect([
                $payment->voucher?->voucher_no ? 'Voucher: ' . $payment->voucher->voucher_no : null,
                $payment->reff_no ? 'Ref: ' . $payment->reff_no : null,
                $payment->transaction_id ? 'Txn: ' . $payment->transaction_id : null,
                filled($payment->remarks) ? $payment->remarks : null,
            ])->filter()->implode(' | ');

            return $from . ' (-) -> ' . $to . ' (+)'
                . ($details !== '' ? ' | ' . $details : '');
        }

        $base = $payment->supplier?->supplier_name ?: 'Supplier Payment';
        $details = collect([
            $payment->voucher?->voucher_no ? 'Voucher: ' . $payment->voucher->voucher_no : null,
            $payment->reff_no ? 'Ref: ' . $payment->reff_no : null,
            $payment->transaction_id ? 'Txn: ' . $payment->transaction_id : null,
            filled($payment->remarks) ? $payment->remarks : null,
        ])->filter()->implode(' | ');

        return $base . ($details !== '' ? ' | ' . $details : '');
    }

    private function statementAccountLabel(BankAccount $account): string
    {
        $parts = collect(explode('|', (string) $account->account_title))
            ->map(fn ($part) => trim($part))
            ->filter();

        return (string) ($parts->last() ?: $account->account_title);
    }

    private function supplierPaymentStatementReference(SupplierPayment $payment): string|int|null
    {
        return $payment->cheque_no
            ?? $payment->slip?->slip_no
            ?? $payment->cheque?->cheque_no
            ?? $payment->transaction_id
            ?? $payment->reff_no;
    }
}
