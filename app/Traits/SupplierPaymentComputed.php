<?php

namespace App\Traits;

use App\Models\CR;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SupplierPaymentComputed
{
    public function issued(): Attribute
    {
        return Attribute::get(function () {
            if (!str_contains(strtolower((string) $this->method), 'program')) {
                return null;
            }

            return ($this->voucher_id || $this->linkedCrReference()) ? 'Issued' : 'Not Issued';
        });
    }

    protected function formatSupplierClearRecord($record)
    {
        return [
            'date' => $record?->clear_date?->format('d-M-Y, D') ?? '-',
            'method' => $record?->method ? ucfirst($record->method) : '-',
            'account_title' => $record?->bankAccount?->account_title ?? '-',
            'bank' => $record?->bankAccount?->bank?->short_title ?? '-',
            'amount' => (float) ($record?->amount ?? 0),
            'reff_no' => $record?->reff_no ?? '-',
            'remarks' => $record?->remarks ?: '-',
        ];
    }

    public function linkedCrReference(): ?CR
    {
        if ($this->cr) {
            return $this->cr;
        }

        static $crBySupplierPaymentId = null;

        if ($crBySupplierPaymentId === null) {
            $crBySupplierPaymentId = [];

            CR::query()
                ->select(['id', 'c_r_no', 'date', 'new_payments'])
                ->get()
                ->each(function (CR $cr) use (&$crBySupplierPaymentId) {
                    foreach ((array) $cr->new_payments as $payment) {
                        $paymentId = null;

                        if (is_array($payment)) {
                            $paymentId = $payment['payment_id'] ?? $payment['data_value'] ?? null;
                        } elseif (is_object($payment)) {
                            $paymentId = $payment->payment_id ?? $payment->data_value ?? null;
                        }

                        if ($paymentId) {
                            $crBySupplierPaymentId[(int) $paymentId] = $cr;
                        }
                    }
                });
        }

        return $crBySupplierPaymentId[(int) $this->id] ?? null;
    }

    /**
     * Voucher number computation
     */
    public function newReffNo(): Attribute
    {
        return Attribute::get(function () {

            // direct relations
            if ($this->cheque) return $this->cheque->cheque_no;
            if ($this->slip) return $this->slip->slip_no;
            if ($this->cheque_no) return $this->cheque_no;
            if ($this->reff_no) return $this->reff_no;
            if ($this->transaction_id) return $this->transaction_id;

            return null;
        });
    }

    public function toFormattedArray()
    {
        $sourceName = '-';
        $sourceType = '-';
        $method = strtolower((string) $this->method);

        if ($this->selfAccount) {
            $sourceName = $this->selfAccount->account_title ?? '-';
            $sourceType = 'Self Account';
        } elseif (in_array($method, ['self cheque', 'self_cheque', 'selfcheque']) && $this->bankAccount) {
            $sourceName = $this->bankAccount->account_title ?? '-';
            $sourceType = 'Self Account';
        } elseif ($this->cheque?->customer) {
            $sourceName = $this->cheque->customer->customer_name ?? '-';
            $sourceType = 'Customer';
        } elseif ($this->slip?->customer) {
            $sourceName = $this->slip->customer->customer_name ?? '-';
            $sourceType = 'Customer';
        } elseif ($this->program?->customer) {
            $sourceName = $this->program->customer->customer_name ?? '-';
            $sourceType = 'Customer';
        } elseif ($this->bankAccount) {
            $sourceName = $this->bankAccount->account_title ?? '-';
            $sourceType = 'Self Account';
        }

        $program = $this->program;
        $cr = $this->linkedCrReference();
        $dr = $this->cheque?->dr ?? $this->slip?->dr;
        $clearDetails = collect();

        if ($this->cheque?->paymentClearRecord) {
            $clearDetails = $this->cheque->paymentClearRecord
                ->sortBy('clear_date')
                ->map(fn($record) => $this->formatSupplierClearRecord($record))
                ->values();
        } elseif ($this->slip?->paymentClearRecord) {
            $clearDetails = $this->slip->paymentClearRecord
                ->sortBy('clear_date')
                ->map(fn($record) => $this->formatSupplierClearRecord($record))
                ->values();
        } elseif ($this->program_id) {
            $matchingCustomerPayments = $program?->customerPayments
                ?->filter(function ($payment) {
                    if ((int) $payment->program_id !== (int) $this->program_id) {
                        return false;
                    }

                    if ($this->transaction_id && $payment->transaction_id !== $this->transaction_id) {
                        return false;
                    }

                    if ($this->bank_account_id && (int) $payment->bank_account_id !== (int) $this->bank_account_id) {
                        return false;
                    }

                    if ((float) $payment->amount !== (float) $this->amount) {
                        return false;
                    }

                    return optional($payment->date)->format('Y-m-d') === optional($this->date)->format('Y-m-d');
                }) ?? collect();

            $clearDetails = $matchingCustomerPayments
                ->flatMap(fn($payment) => $payment->paymentClearRecord ?? collect())
                ->sortBy('clear_date')
                ->map(fn($record) => $this->formatSupplierClearRecord($record))
                ->values();
        }

        return [
            'id' => $this->id,
            'name' => $this->supplier->supplier_name ?? app('client_company')->name,
            'method' => $this->method,
            'date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
            'amount' => \App\Support\Money::format($this->amount),
            'amount_numeric' => (float) $this->amount,
            'reff_no' => $this->new_reff_no,
            'voucher_no' => $this->voucher->voucher_no ?? $cr?->c_r_no ?? '-',
            'issued' => $this->issued,
            'source_name' => $sourceName,
            'source_type' => $sourceType,
            'bank_account' => $this->bankAccount ? [
                'account_title' => $this->bankAccount->account_title,
                'bank' => $this->bankAccount->bank?->short_title,
            ] : null,
            'self_account' => $this->selfAccount ? [
                'account_title' => $this->selfAccount->account_title,
                'bank' => $this->selfAccount->bank?->short_title,
            ] : null,
            'transaction_id' => $this->transaction_id,
            'remarks' => $this->remarks,
            'program_no' => $program?->program_no,
            'program_order_no' => $program?->order_no,
            'program_date' => $program?->date ? $program->date->format('d-M-Y, D') : null,
            'program_customer' => $program?->customer?->customer_name ?? null,
            'cr_no' => $cr?->c_r_no,
            'cr_date' => $cr?->date ? $cr->date->format('d-M-Y, D') : null,
            'dr_no' => $dr?->d_r_no,
            'dr_date' => $dr?->date ? $dr->date->format('d-M-Y, D') : null,
            'clear_details' => $clearDetails,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->whereHas('supplier', function ($q) use ($value) {
                    $q->where('supplier_name', 'like', "%$value%");
                })

                // Case 2: supplier does NOT exist → fallback to client_company name
                ->orWhere(function ($q) use ($value) {
                    $q->whereDoesntHave('supplier')
                    ->whereRaw('? LIKE ?', [app('client_company')->name, "%{$value}%"]);
                });

            case 'method':
                return $query->whereRaw('LOWER(method) = ?', [strtolower((string) $value)]);

            case 'issued':
                $issuedValueRaw = strtolower(trim((string) $value));
                $issuedValue = str_replace('_', ' ', explode(':', $issuedValueRaw, 2)[0]);

                return $query->whereRaw('LOWER(method) LIKE ?', ['%program%'])
                    ->when($issuedValue === 'issued', function ($q) {
                        $q->where(function ($sq) {
                            $sq->whereNotNull('voucher_id')
                                ->orWhereNotNull('c_r_id')
                                ->orWhereRaw('LOWER(method) LIKE ?', ['%cr%']);
                        });
                    })
                    ->when($issuedValue === 'not issued', function ($q) {
                        $q->whereNull('voucher_id')
                            ->whereNull('c_r_id')
                            ->whereRaw('LOWER(method) NOT LIKE ?', ['%cr%']);
                    })
                    ->when(!in_array($issuedValue, ['issued', 'not issued'], true), function ($q) {
                        $q->whereRaw('1=0');
                    });

            case 'reff_no':
                return $query->where(function ($q) use ($value) {

                    $q->whereHas('cheque', function ($q2) use ($value) {
                        $q2->where('cheque_no', 'like', "%$value%");
                    })

                    ->orWhereHas('slip', function ($q2) use ($value) {
                        $q2->where('slip_no', 'like', "%$value%");
                    })

                    ->orWhere('transaction_id', 'like', "%$value%")
                    ->orWhere('cheque_no', 'like', "%$value%")
                    ->orWhere('reff_no', 'like', "%$value%");
                });


            case 'voucher_no':
                return $query->where(function ($q) use ($value) {
                    $q->whereHas('voucher', function ($sq) use ($value) {
                        $sq->where('voucher_no', 'like', "%$value%");
                    })
                    ->orWhereHas('cr', function ($sq) use ($value) {
                        $sq->where('c_r_no', 'like', "%$value%");
                    });
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
