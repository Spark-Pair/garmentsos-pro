<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CustomerPaymentComputed
{
    /**
     * Voucher number computation
     */
    public function voucherNo(): Attribute
    {
        return Attribute::get(function () {

            // direct relations
            if ($this->cheque?->voucher) return $this->cheque->voucher->voucher_no;
            if ($this->slip?->voucher)   return $this->slip->voucher->voucher_no;
            if ($this->cheque?->cr)      return $this->cheque->cr->c_r_no;
            if ($this->slip?->cr)        return $this->slip->cr->c_r_no;
            if ($this->dr)               return $this->dr->d_r_no;

            // program-based fallback
            if ($this->program_id) {
                $supplierPayment = SupplierPayment::with('voucher')
                    ->where('program_id', $this->program_id)
                    ->where('bank_account_id', $this->bank_account_id)
                    ->where('transaction_id', $this->transaction_id)
                    ->where('amount', $this->amount)
                    ->whereDate('date', $this->date)
                    ->first();

                return $supplierPayment?->voucher?->voucher_no ?? null;
            }

            return null;
        });
    }

    public function supplierName(): Attribute
    {
        return Attribute::get(function () {

            if ($this->cheque?->supplier) return $this->cheque->supplier->supplier_name;
            if ($this->slip?->supplier)   return $this->slip->supplier->supplier_name;
            if ($this->program && $this->program?->subCategory)   return $this->program?->subCategory?->supplier_name;
            if ($this->cheque?->voucher?->supplier) return $this->cheque?->voucher?->supplier->supplier_name;
            if ($this->slip?->voucher?->supplier)   return $this->slip?->voucher?->supplier->supplier_name;
            if ($this->bankAccount)   return $this->bankAccount->account_title;

            return '-';
        });
    }

    public function beneficiary(): Attribute
    {
        return Attribute::get(function () {

            if ($this->cheque?->supplier) return $this->cheque->supplier->supplier_name;
            if ($this->slip?->supplier)   return $this->slip->supplier->supplier_name;
            if ($this->bankAccount)   return $this->bankAccount?->account_title;
            if ($this->cheque?->voucher?->supplier) return $this->cheque?->voucher?->supplier->supplier_name;
            if ($this->slip?->voucher?->supplier)   return $this->slip?->voucher?->supplier->supplier_name;
            if ($this->bankAccount?->subCategory)   return $this->bankAccount?->subCategory->supplier_name;

            return '-';
        });
    }

    public function reffNo(): Attribute
    {
        return Attribute::get(function () {
            return $this->cheque_no
                ?? $this->slip_no
                ?? $this->transaction_id
                ?? $this->reff_no
                ?? '-';
        });
    }

    public function clearanceDate(): Attribute
    {
        return Attribute::get(function () {
            // Show clearance only when cheque OR slip exists
            if ($this->cheque_no !== null || $this->slip_no !== null) {

                // Prefer direct clear_date
                if ($this->clear_date) {
                    return $this->clear_date->format('d-M-Y, D');
                }

                // Otherwise check latest payment clear record
                $last = $this->paymentClearRecord->last();

                if ($last?->clear_date) {
                    return $last->clear_date->format('d-M-Y, D');
                }

                // Still not cleared
                return 'Pending';
            }

            // If neither cheque nor slip, return null
            return null;
        });
    }

    public function clearedAmount(): Attribute
    {
        return Attribute::get(function () {
            if ($this->cheque_no !== null || $this->slip_no !== null) {
                if ($this->clear_date !== null) {
                    return $this->amount;
                } else {
                    return $this->paymentClearRecord->sum('amount');
                }
            } else {
                return '-';
            }
        });
    }

    public function category(): Attribute
    {
        return Attribute::get(function () {
            return $this->customer->category == 'cash' ? 'cash' : 'non-cash';
        });
    }

    public function issued(): Attribute
    {
        return Attribute::get(function () {
            if ((($this->cheque || $this->slip) || in_array($this->method, ['cheque','slip']) && $this->bank_account_id) && !$this->is_return) {
                return 'Issued';
            } elseif ($this->is_return && $this->d_r_id === null) {
                return 'Return';
            } else {
                return 'Not Issued';
            }

            if ($this->d_r_id !== null) {
                return 'DR';
            }

            return null;
        });
    }

    public function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->cheque_no !== null || $this->slip_no !== null) {
                if ($this->cleared_amount >= $this->amount) {
                    return 'cleared';
                } else {
                    return 'pending';
                }
            } else {
                return null;
            }
        });
    }

    public function drNo(): Attribute
    {
        return Attribute::get(function () {
            return $this->dr ? $this->dr?->d_r_no : '-';
        });
    }

    public function hasPipe(): Attribute
    {
        return Attribute::get(function () {

            $raw = match ($this->method) {
                'cheque'  => $this->cheque_no,
                'slip'    => $this->slip_no,
                'program' => $this->transaction_id,
                default   => $this->reff_no,
            };

            return $raw && str_contains($raw, '|');
        });
    }

    public function maxReffSuffix(): Attribute
    {
        return Attribute::get(function () {

            $raw = match ($this->method) {
                'cheque'  => $this->cheque_no,
                'slip'    => $this->slip_no,
                'program' => $this->transaction_id,
                default   => $this->reff_no,
            };

            if (!$raw) return 0;

            $baseRef = trim(explode('|', $raw)[0]);
            if (!$baseRef) return 0;

            $query = self::query();

            // 🔑 select correct column
            $column = match ($this->method) {
                'cheque'  => 'cheque_no',
                'slip'    => 'slip_no',
                'program' => 'transaction_id',
                default   => 'reff_no',
            };

            // only refs with same base + pipe
            $refs = $query
                ->where($column, 'like', $baseRef . '%|%')
                ->pluck($column);

            $max = 0;

            foreach ($refs as $ref) {
                [, $n] = array_map('trim', explode('|', $ref, 2));
                if (is_numeric($n)) {
                    $max = max($max, (int)$n);
                }
            }

            return $max;
        });
    }

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
            'details' => [
                'Type' => $this->type,
                'Method' => $this->method,
                'Date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
                'Amount' => $this->amount,
            ],
            'method' => $this->method,
            'data' => $this,
            'date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
            'voucher_no' => $this->voucher_no ?? '-',
            'supplier_name' => $this->supplier_name,
            'reff_no' => $this->reff_no,
            'beneficiary' => $this->beneficiary,
            'clear_date' => $this->clearance_date,
            'cleared_amount' => $this->cleared_amount,
            'category' => $this->category,
            'issued' => $this->issued,
            'status' => $this->status,
            'd_r_no' => $this->dr_no,
            'has_pipe' => $this->has_pipe,
            'max_reff_suffix' => $this->max_reff_suffix,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%$value%")
                    ->orWhereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });

            case 'city':
                return $query->whereHas('customer.city', function ($q) use ($value) {
                    // Nested function isliye taake 'OR' condition sirf city ke andar rahe
                    $q->where(function($sq) use ($value) {
                        $sq->where('title', 'like', "%{$value}%")
                        ->orWhere('short_title', 'like', "%{$value}%");
                    });
                });

case 'voucher_no':
    return $query->where(function ($q) use ($value) {

        // cheque / slip vouchers
        $q->whereHas('cheque.voucher', fn ($sq) =>
                $sq->where('voucher_no', 'like', "%$value%")
            )
          ->orWhereHas('slip.voucher', fn ($sq) =>
                $sq->where('voucher_no', 'like', "%$value%")
            )

        // cheque / slip CR
          ->orWhereHas('cheque.cr', fn ($sq) =>
                $sq->where('c_r_no', 'like', "%$value%")
            )
          ->orWhereHas('slip.cr', fn ($sq) =>
                $sq->where('c_r_no', 'like', "%$value%")
            )

        // DR
          ->orWhereHas('dr', fn ($sq) =>
                $sq->where('d_r_no', 'like', "%$value%")
            )

        // 🔥 PROGRAM BASED SUPPLIER VOUCHER (FIX)
          ->orWhereExists(function ($sq) use ($value) {
                $sq->selectRaw(1)
                    ->from('supplier_payments')
                    ->join('vouchers', 'vouchers.id', '=', 'supplier_payments.voucher_id')
                    ->whereColumn('supplier_payments.program_id', 'customer_payments.program_id')
                    ->whereColumn('supplier_payments.bank_account_id', 'customer_payments.bank_account_id')
                    ->whereColumn('supplier_payments.transaction_id', 'customer_payments.transaction_id')
                    ->whereColumn('supplier_payments.amount', 'customer_payments.amount')
                    ->whereRaw('DATE(supplier_payments.date) = DATE(customer_payments.date)')
                    ->where('vouchers.voucher_no', 'like', "%$value%");
            });
    });

            case 'beneficiary':
                return $query->where(function($q) use ($value) {
                    $q->whereHas('cheque.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('slip.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('bankAccount', fn($sq) => $sq->where('account_title', 'like', "%$value%"))
                    ->orWhereHas('cheque.voucher.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('slip.voucher.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"));
                });

            case 'supplier_name':
                return $query->where(function($q) use ($value) {
                    $q->whereHas('cheque.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('slip.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('cheque.voucher.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('slip.voucher.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"));
                });

            case 'reff_no':
                return $query->where(function($q) use ($value) {
                    $q->where('cheque_no', 'like', "%$value%")
                    ->orWhere('slip_no', 'like', "%$value%")
                    ->orWhere('transaction_id', 'like', "%$value%")
                    ->orWhere('reff_no', 'like', "%$value%");
                });

            case 'status':
                return $query->where(function($q) use ($value) {
                    // Condition: Sirf wahi records jin mein cheque ya slip no ho
                    $q->where(function($sq) {
                        $sq->whereNotNull('cheque_no')->orWhereNotNull('slip_no');
                    });

                    $statusValue = strtolower($value);

                    // Aapke model ke mutabiq table names aur foreign keys:
                    // Table: payment_clears
                    // Foreign Key: payment_id
                    $subQuerySql = "(SELECT COALESCE(SUM(amount), 0) FROM payment_clears WHERE payment_clears.payment_id = customer_payments.id)";

                    if ($statusValue == 'cleared') {
                        $q->where(function($sq) use ($subQuerySql) {
                            $sq->whereNotNull('clear_date')
                            ->orWhereRaw("$subQuerySql >= customer_payments.amount");
                        });
                    } elseif ($statusValue == 'pending') {
                        $q->whereNull('clear_date')
                        ->whereRaw("$subQuerySql < customer_payments.amount");
                    }
                });

            case 'issued':
                return $query->where(function($q) use ($value) {
                    if ($value == 'Issued') {
                        $q->where(function($sq) {
                            // PHP Logic: (($this->cheque || $this->slip) || in_array($this->method, ['cheque','slip']) && $this->bank_account_id)
                            $sq->whereHas('cheque')
                            ->orWhereHas('slip')
                            ->orWhere(function($ssq) {
                                $ssq->whereIn('method', ['cheque', 'slip'])
                                    ->whereNotNull('bank_account_id');
                            });
                        })->where('is_return', 0);
                    }
                    elseif ($value == 'Return') {
                        // PHP Logic: $this->is_return && $this->d_r_id === null
                        $q->where('is_return', 1)->whereNull('d_r_id');
                    }
                    elseif ($value == 'DR') {
                        // PHP Logic: $this->d_r_id !== null
                        $q->whereNotNull('d_r_id');
                    }
                    elseif ($value == 'Not Issued') {
                        // Not Issued wo hai jo upar ki kisi condition mein na aata ho
                        $q->where('is_return', 0)
                        ->whereDoesntHave('cheque')
                        ->whereDoesntHave('slip')
                        ->where(function($sq) {
                            $sq->whereNotIn('method', ['cheque', 'slip'])
                                ->orWhereNull('bank_account_id');
                        });
                    }
                });

            case 'category':
                return $query->whereHas('customer', function($q) use ($value) {
                    if ($value == 'cash') $q->where('category', 'cash');
                    else $q->where('category', '!=', 'cash');
                });

            case 'method':
                return $query->where('method', $value);

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query->where('method', 'cash');


                return $query->where(function ($q) use ($start, $end) {
                    // 1️⃣ slip_date exists
                    $q->where(function ($q) use ($start, $end) {
                        $q->whereNotNull('slip_date')
                        ->whereBetween('slip_date', [$start.' 00:00:00', $end.' 23:59:59']);
                    })
                    // 2️⃣ slip_date null, cheque_date exists
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('slip_date')
                        ->whereNotNull('cheque_date')
                        ->whereBetween('cheque_date', [$start.' 00:00:00', $end.' 23:59:59']);
                    })
                    // 3️⃣ slip_date null, cheque_date null, fallback to date
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('slip_date')
                        ->whereNull('cheque_date')
                        ->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
                    });
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
