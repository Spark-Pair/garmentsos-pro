<?php

namespace App\Models;

use App\Traits\CustomerComputed;
use App\Traits\Filterable;
use App\Services\Orders\OrderBalanceService;
use App\Support\DateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Customer extends Model
{
    use HasFactory;

    use Filterable, CustomerComputed;

    protected $fillable = [
        'user_id',
        'branch_id',
        'customer_name',
        'person_name',
        'urdu_title',
        'phone_number',
        'date',
        'category',
        'city_id',
        'address',
    ];

    protected $hidden = [
        'user_id',
        'creator_id',
        'city_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    protected $appends = ['balance'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function city()
    {
        return $this->belongsTo(Setup::class, 'city_id', 'id')->where('type', 'city');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'customer_id');
    }

    public function paymentPrograms()
    {
        return $this->hasMany(PaymentProgram::class, 'customer_id');
    }

    public function bankAccounts()
    {
        return $this->morphMany(BankAccount::class, 'sub_category');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function statementAdjustments()
    {
        return $this->morphMany(StatementAdjustment::class, 'adjustable');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }
    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true, ?array $branchIds = null, bool $includeNullBranchRecords = false)
    {
        $invoicesQuery = $this->invoices();

        // Sales returns are authoritative for return credits. Their
        // customer_payment rows are excluded here to prevent double counting.
        $paymentsQuery = $this->payments()
            ->where('type', '!=', 'DR')
            ->where('type', '!=', 'sales_return');

        $salesReturnsQuery = SalesReturn::whereHas('invoice', function ($query) {
            $query->where('customer_id', $this->id);
        });

        $adjustmentsQuery = $this->statementAdjustments();

        $branchIds = collect($branchIds ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $hasBranchScope = count($branchIds) > 0;

        if ($hasBranchScope && Schema::hasColumn('invoices', 'branch_id')) {
            $invoicesQuery->where(function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $query->orWhereNull('branch_id');
                }
            });
        }

        if ($hasBranchScope && Schema::hasColumn('customer_payments', 'branch_id')) {
            $paymentsQuery->where(function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $query->orWhereNull('branch_id');
                }
            });
        }

        if ($hasBranchScope && Schema::hasColumn('sales_returns', 'branch_id')) {
            $salesReturnsQuery->where(function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $query->orWhereNull('branch_id');
                }
            });
        }

        if ($hasBranchScope && Schema::hasColumn('statement_adjustments', 'branch_id')) {
            $adjustmentsQuery->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                $nested->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $nested->orWhereNull('branch_id');
                }
            });
        }

        DateRange::apply($invoicesQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($paymentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($salesReturnsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($adjustmentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);

        $totalInvoices = $invoicesQuery->sum('netAmount') ?? 0;
        $totalPayments = ($paymentsQuery->sum('amount') ?? 0)
            + ($salesReturnsQuery->sum('amount') ?? 0);

        $adjustmentsNet = (float) $adjustmentsQuery
            ->get()
            ->sum(fn ($adjustment) => (float) $adjustment->net_amount);

        $balance = ($totalInvoices - $totalPayments) + $adjustmentsNet;

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
        // 🧮 Opening & Closing Balances
        $openingBalance = $this->calculateBalance(null, $fromDate, false, false, $branchIds, $includeNullBranchRecords);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate, false, true, $branchIds, $includeNullBranchRecords);
        $closingBalance = $openingBalance + $periodBalance;

        // --- Normalize dates ---
        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->endOfDay();

        // --- Fetch invoices & payments ---
        $invoices = $this->invoices()
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->when($hasBranchScope && Schema::hasColumn('invoices', 'branch_id'), fn ($query) => $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                $nested->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $nested->orWhereNull('branch_id');
                }
            }))
            ->get();

        // Sales-return payments are rendered from sales_returns directly.
        // This keeps old returns visible even if their CustomerPayment row
        // is missing or has a stale/wrong branch_id.
        $payments = $this->payments()
            ->where('type', '!=', 'DR')
            ->where('type', '!=', 'sales_return')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->when($hasBranchScope && Schema::hasColumn('customer_payments', 'branch_id'), fn ($query) => $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                $nested->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $nested->orWhereNull('branch_id');
                }
            }))
            ->get();

        $salesReturns = SalesReturn::with(['article', 'invoice'])
            ->whereHas('invoice', function ($query) {
                $query->where('customer_id', $this->id);
            })
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->when($hasBranchScope && Schema::hasColumn('sales_returns', 'branch_id'), function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                    $nested->whereIn('branch_id', $branchIds);
                    if ($includeNullBranchRecords) {
                        $nested->orWhereNull('branch_id');
                    }
                });
            })
            ->get();

        $adjustments = $this->statementAdjustments()
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->when($hasBranchScope && Schema::hasColumn('statement_adjustments', 'branch_id'), function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                    $nested->whereIn('branch_id', $branchIds);
                    if ($includeNullBranchRecords) {
                        $nested->orWhereNull('branch_id');
                    }
                });
            })
            ->get();

        $statement = collect();
        $formatAdjustment = function ($adjustment) {
            $isPlus = $adjustment->direction === 'plus';
            $label = $adjustment->entry_type === 'opening_balance' ? 'Opening Balance' : 'Adjustment';

            return [
                'date' => $adjustment->date,
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

        $paymentDescription = function ($p) {
            if ($p->type === 'sales_return') {
                return $p->remarks ?: 'Sales return';
            }

            return $p->cheque_date?->format('d-M-Y, D')
                ?? $p->slip_date?->format('d-M-Y, D')
                ?? (($p->bankAccount?->account_title || $p->bankAccount?->bank?->short_title)
                    ? trim(
                        ($p->bankAccount?->account_title ?? '') .
                        ($p->bankAccount?->bank?->short_title
                            ? ' | ' . $p->bankAccount->bank->short_title
                            : ''),
                        ' |'
                    )
                    : null);
        };

        $salesReturnRow = function ($r) {
            $prefix = $r->type === 'adjustment' ? 'ADJ-' : 'SR-';

            return [
                'date' => $r->date,
                'reff_no' => $prefix . $r->id,
                'type' => 'payment',
                'method' => 'return',
                'payment' => (float) $r->amount,
                'bill' => 0,
                'description' => $r->type === 'adjustment' ? 'Sales adjustment' : 'Sales return',
                'created_at' => $r->created_at,
                'source' => [
                    'type' => 'sales_return',
                    'id' => $r->id,
                ],
            ];
        };

        $invoiceDescription = function ($i) {
            $biltyNo = $i->bilty? $i->bilty->bilty_no : '-';
            $cargoName = $i->cargo? $i->cargo->cargo_name : '-';
            return "$biltyNo | $cargoName";
        };

        if ($type === 'summarized') {
            // 🔹 Group invoices by date
            $invoiceGrouped = $invoices->groupBy(fn($i) => Carbon::parse($i->date)->toDateString());
            // 🔹 Group payments by date
            $paymentGrouped = $payments->groupBy(fn($p) => Carbon::parse($p->date)->toDateString());
            $salesReturnGrouped = $salesReturns->groupBy(fn($r) => Carbon::parse($r->date)->toDateString());
            $adjustmentGrouped = $adjustments->groupBy(fn($a) => Carbon::parse($a->date)->toDateString());

            // 🔹 Get all unique dates
            $allDates = $invoiceGrouped->keys()
                ->merge($paymentGrouped->keys())
                ->merge($salesReturnGrouped->keys())
                ->merge($adjustmentGrouped->keys())
                ->unique()
                ->sort();

            foreach ($allDates as $date) {
                $bill = isset($invoiceGrouped[$date]) ? $invoiceGrouped[$date]->sum(fn($i) => (float) $i->netAmount) : 0;
                $payment = isset($paymentGrouped[$date]) ? $paymentGrouped[$date]->sum(fn($p) => (float) $p->amount) : 0;
                $payment += isset($salesReturnGrouped[$date]) ? $salesReturnGrouped[$date]->sum(fn($r) => (float) $r->amount) : 0;
                $dayAdjustments = $adjustments->filter(fn($adjustment) => Carbon::parse($adjustment->date)->toDateString() === $date);
                $adjustmentBill = $dayAdjustments->where('direction', 'plus')->sum('amount');
                $adjustmentPayment = $dayAdjustments->where('direction', 'minus')->sum('amount');
                $bill += $adjustmentBill;
                $payment += $adjustmentPayment;

                // Add payment first if exists
                if ($payment > 0) {
                    $firstPayment = $paymentGrouped[$date]->sortBy('created_at')->first() ?? $dayAdjustments->where('direction', 'minus')->sortBy('created_at')->first();
                    $statement->push([
                        'type' => 'payment',
                        'date' => Carbon::parse($date),
                        'bill' => 0,
                        'payment' => $payment,
                        'created_at' => $firstPayment->created_at,
                    ]);
                }

                // Add invoice
                if ($bill > 0) {
                    $firstInvoice = $invoiceGrouped[$date]->sortBy('created_at')->first() ?? $dayAdjustments->where('direction', 'plus')->sortBy('created_at')->first();
                    $statement->push([
                        'type' => 'invoice',
                        'date' => Carbon::parse($date),
                        'bill' => $bill,
                        'payment' => 0,
                        'created_at' => $firstInvoice->created_at,
                    ]);
                }
            }

            // Sort by date then created_at
            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        } elseif ($type === 'general') {
            // 🔹 Detailed invoices + voucher-wise payments
            foreach ($invoices as $i) {
                $statement->push([
                    'date' => $i->date,
                    'reff_no' => $i->invoice_no,
                    'type' => 'invoice',
                    'method' => 'Invoice',
                    'bill' => (float) $i->netAmount,
                    'payment' => 0,
                    'created_at' => $i->created_at,
                    'source' => [
                        'type' => 'invoice',
                        'id' => $i->id,
                    ],
                ]);
            }

            $paymentGroups = $payments->groupBy(function ($p) {
                return $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no ?? $p->id;
            });

            foreach ($paymentGroups as $voucherNo => $group) {
                $first = $group->sortBy('created_at')->first();
                $statement->push([
                    'date' => $first->date,
                    'reff_no' => $voucherNo,
                    'type' => 'payment',
                    'method' => $first->method,
                    'payment' => (float) $group->sum('amount'),
                    'bill' => 0,
                    'description' => $paymentDescription($first),
                    'created_at' => $first->created_at,
                    'source' => [
                        'type' => 'customer_payment',
                        'id' => $first->id,
                    ],
                ]);
            }

            foreach ($salesReturns as $salesReturn) {
                $statement->push($salesReturnRow($salesReturn));
            }

            foreach ($adjustments as $adjustment) {
                $statement->push($formatAdjustment($adjustment));
            }

            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        } else {
            // 🔹 Detailed mode
            foreach ($invoices as $i) {
                $statement->push([
                    'date' => $i->date,
                    'reff_no' => $i->invoice_no,
                    'type' => 'invoice',
                    'method' => 'Invoice',
                    'bill' => (float) $i->netAmount,
                    'payment' => 0,
                    'description' => $invoiceDescription($i),
                    'created_at' => $i->created_at,
                    'source' => [
                        'type' => 'invoice',
                        'id' => $i->id,
                    ],
                ]);
            }

            foreach ($payments as $p) {
                $statement->push([
                    'date' => $p->date,
                    'reff_no' => $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no,
                    'type' => 'payment',
                    'method' => $p->method,
                    'payment' => (float) $p->amount,
                    'bill' => 0,
                    'description' => $paymentDescription($p),
                    'created_at' => $p->created_at,
                    'source' => [
                        'type' => 'customer_payment',
                        'id' => $p->id,
                    ],
                ]);
            }

            foreach ($salesReturns as $salesReturn) {
                $statement->push($salesReturnRow($salesReturn));
            }

            foreach ($adjustments as $adjustment) {
                $statement->push($formatAdjustment($adjustment));
            }

            // Sort by date then created_at
            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        }

        // 📊 Totals
        $totals = [
            'bill' => $statement->sum('bill'),
            'payment' => $statement->sum('payment'),
            'balance' => $statement->sum('bill') - $statement->sum('payment'),
            'order_balance' => app(OrderBalanceService::class)->pendingForCustomer(
                $this,
                $branchIds,
                $includeNullBranchRecords,
            ),
        ];
        $statementDateRange = $statement->isNotEmpty()
            ? Carbon::parse($statement->min('date'))->format('d-M-Y') . ' - ' . Carbon::parse($statement->max('date'))->format('d-M-Y')
            : $from->format('d-M-Y') . ' - ' . $to->format('d-M-Y');

        return [
            'date' => $statementDateRange,
            'name' => $this->customer_name,
            'customer' => [
                'name' => $this->customer_name,
                'address' => $this->address,
                'city' => [
                    'id' => $this->city?->id,
                    'title' => $this->city?->title,
                    'short_title' => $this->city?->short_title,
                ],
            ],
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => $totals,
            'category' => 'customer',
        ];
    }
}