<?php

namespace App\Models;

use App\Traits\CustomerComputed;
use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    use HasFactory;

    use Filterable, CustomerComputed;

    protected $fillable = [
        'user_id',
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

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }
    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $invoicesQuery = $this->invoices()->whereNotNull('shipment_no');
        $paymentsQuery = $this->payments()->where('type', '!=', 'DR');

        // Normalize dates to start/end of day
        if ($fromDate) {
            $from = Carbon::parse($fromDate)->startOfDay();
        }
        if ($toDate) {
            $to = Carbon::parse($toDate)->endOfDay();
        }

        // Handle different date scenarios
        if (isset($from, $to)) {
            if ($includeGivenDate) {
                $invoicesQuery->whereBetween('date', [$from, $to]);
                $paymentsQuery->whereBetween('date', [$from, $to]);
            } else {
                $invoicesQuery->where('date', '>', $from)->where('date', '<', $to);
                $paymentsQuery->where('date', '>', $from)->where('date', '<', $to);
            }
        } elseif (isset($from)) {
            $operator = $includeGivenDate ? '>=' : '>';
            $invoicesQuery->where('date', $operator, $from);
            $paymentsQuery->where('date', $operator, $from);
        } elseif (isset($to)) {
            $operator = $includeGivenDate ? '<=' : '<';
            $invoicesQuery->where('date', $operator, $to);
            $paymentsQuery->where('date', $operator, $to);
        }

        // Calculate totals
        $totalInvoices = $invoicesQuery->sum('netAmount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;

        $balance = $totalInvoices - $totalPayments;

        return $formatted ? number_format($balance, 1, '.', ',') : $balance;
    }
    public function getStatement($fromDate, $toDate, $type = 'summarized')
    {
        // 🧮 Opening & Closing Balances
        $openingBalance = $this->calculateBalance(null, $fromDate, false, false);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate);
        $closingBalance = $openingBalance + $periodBalance;

        // --- Normalize dates ---
        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->endOfDay();

        // --- Fetch invoices & payments ---
        $invoices = $this->invoices()
            ->whereBetween('date', [$from, $to])
            ->get();

        $payments = $this->payments()
            ->where('type', '!=', 'DR')
            ->whereBetween('date', [$from, $to])
            ->get();

        $statement = collect();

        if ($type === 'summarized') {
            // 🔹 Group invoices by date
            $invoiceGrouped = $invoices->groupBy(fn($i) => Carbon::parse($i->date)->toDateString());
            // 🔹 Group payments by date
            $paymentGrouped = $payments->groupBy(fn($p) => Carbon::parse($p->date)->toDateString());

            // 🔹 Get all unique dates
            $allDates = $invoiceGrouped->keys()->merge($paymentGrouped->keys())->unique()->sort();

            foreach ($allDates as $date) {
                $bill = isset($invoiceGrouped[$date]) ? $invoiceGrouped[$date]->sum(fn($i) => (float) $i->netAmount) : 0;
                $payment = isset($paymentGrouped[$date]) ? $paymentGrouped[$date]->sum(fn($p) => (float) $p->amount) : 0;

                // Add payment first if exists
                if ($payment > 0) {
                    $firstPayment = $paymentGrouped[$date]->sortBy('created_at')->first();
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
                    $firstInvoice = $invoiceGrouped[$date]->sortBy('created_at')->first();
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
        } else {
            // 🔹 Detailed mode
            foreach ($invoices as $i) {
                $statement->push([
                    'date' => $i->date,
                    'reff_no' => $i->invoice_no,
                    'type' => 'invoice',
                    'bill' => (float) $i->netAmount,
                    'payment' => 0,
                    'created_at' => $i->created_at,
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
                    'description' => $p->cheque_date?->format('d-M-Y, D')
                                    ?? $p->slip_date?->format('d-M-Y, D')
                                    ?? (($p->bankAccount?->account_title || $p->bankAccount?->bank?->short_title)
                                        ? trim(
                                            ($p->bankAccount?->account_title ?? '') .
                                            ($p->bankAccount?->bank?->short_title
                                                ? ' | ' . $p->bankAccount->bank->short_title
                                                : ''),
                                            ' |'
                                        )
                                        : null),
                    'created_at' => $p->created_at,
                ]);
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
        ];

        return [
            'date' => $from->format('d-M-Y') . ' - ' . $to->format('d-M-Y'),
            'name' => "{$this->customer_name} | {$this->city->title}",
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => $totals,
            'category' => 'customer',
        ];
    }
}
