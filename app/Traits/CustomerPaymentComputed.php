<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CustomerPaymentComputed
{
    protected function isProgramMethod(): bool
    {
        return strtolower((string) $this->method) === 'program';
    }

    protected static function whereNullableColumnMatches($query, string $leftColumn, string $rightColumn): void
    {
        $query->where(function ($q) use ($leftColumn, $rightColumn) {
            $q->whereColumn($leftColumn, $rightColumn)
                ->orWhere(function ($sq) use ($leftColumn, $rightColumn) {
                    $sq->whereNull($leftColumn)->whereNull($rightColumn);
                });
        });
    }

    protected static function addMatchingProgramSupplierPaymentConstraints($query): void
    {
        $query->whereRaw('LOWER(supplier_payments.method) LIKE ?', ['%program%'])
            ->whereColumn('supplier_payments.program_id', 'customer_payments.program_id')
            ->where(function ($matchQ) {
                $matchQ->where(function ($exactQ) {
                    $exactQ->whereColumn('supplier_payments.amount', 'customer_payments.amount')
                        ->whereRaw('DATE(supplier_payments.date) = DATE(customer_payments.date)');

                    self::whereNullableColumnMatches($exactQ, 'supplier_payments.bank_account_id', 'customer_payments.bank_account_id');
                    self::whereNullableColumnMatches($exactQ, 'supplier_payments.transaction_id', 'customer_payments.transaction_id');
                })
                ->orWhere(function ($refAmountQ) {
                    self::whereMeaningfulTransactionMatches($refAmountQ);
                    $refAmountQ->whereColumn('supplier_payments.amount', 'customer_payments.amount');
                })
                ->orWhere(function ($zeroRefQ) {
                    self::whereCustomerTransactionIsNotMeaningful($zeroRefQ);
                    self::whereSupplierTransactionIsNotMeaningful($zeroRefQ);
                    $zeroRefQ->whereColumn('supplier_payments.amount', 'customer_payments.amount')
                        ->whereRaw('DATE(supplier_payments.date) = DATE(customer_payments.date)');
                });
            });
    }

    protected function matchingProgramSupplierPaymentQuery()
    {
        return SupplierPayment::query()
            ->whereRaw('LOWER(method) LIKE ?', ['%program%'])
            ->where('program_id', $this->program_id)
            ->where('bank_account_id', $this->bank_account_id)
            ->where('transaction_id', $this->transaction_id)
            ->where('amount', $this->amount)
            ->whereDate('date', $this->date);
    }

    protected function findMatchingProgramSupplierPayment()
    {
        if (!$this->program_id) {
            return null;
        }

        static $programVoucherPayments = null;

        $supplierPayments = $this->program?->relationLoaded('supplierPayments')
            ? $this->program->supplierPayments
                ->filter(function ($payment) {
                    $method = strtolower((string) $payment->method);

                    return str_contains($method, 'program')
                        && ($payment->voucher_id || $payment->c_r_id || str_contains($method, 'cr'));
                })
                ->values()
            : null;

        if (!$supplierPayments) {
            if ($programVoucherPayments === null) {
                $programVoucherPayments = SupplierPayment::with(['voucher', 'cr'])
                    ->whereRaw('LOWER(method) LIKE ?', ['%program%'])
                    ->whereNotNull('program_id')
                    ->where(function ($query) {
                        $query->whereNotNull('voucher_id')
                            ->orWhereNotNull('c_r_id')
                            ->orWhereRaw('LOWER(method) LIKE ?', ['%cr%']);
                    })
                    ->get()
                    ->groupBy('program_id');
            }

            $supplierPayments = $programVoucherPayments->get($this->program_id, collect())->values();
        }

        $matchesBase = function ($payment): bool {
            return (int) $payment->program_id === (int) $this->program_id
                && (float) $payment->amount === (float) $this->amount;
        };

        $exact = $supplierPayments
            ->first(function ($payment) use ($matchesBase) {
            return $matchesBase($payment)
                && (int) ($payment->bank_account_id ?? 0) === (int) ($this->bank_account_id ?? 0)
                && (string) ($payment->transaction_id ?? '') === (string) ($this->transaction_id ?? '')
                && optional($payment->date)->format('Y-m-d') === optional($this->date)->format('Y-m-d');
        });

        if ($exact) {
            return $exact;
        }

        $transactionId = trim((string) $this->transaction_id);
        if ($transactionId !== '' && $transactionId !== '0') {
            $byReferenceAndAmount = $supplierPayments
                ->first(fn ($payment) =>
                $matchesBase($payment)
                && (string) ($payment->transaction_id ?? '') === (string) $this->transaction_id
            );

            if ($byReferenceAndAmount) {
                return $byReferenceAndAmount;
            }
        }

        if ($transactionId === '' || $transactionId === '0') {
            return $supplierPayments
                ->first(function ($payment) use ($matchesBase) {
                $supplierTransactionId = trim((string) $payment->transaction_id);

                return in_array($supplierTransactionId, ['', '0'], true)
                    && $matchesBase($payment)
                    && optional($payment->date)->format('Y-m-d') === optional($this->date)->format('Y-m-d');
            });
        }

        return null;
    }

    protected function hasProgramVoucher(): bool
    {
        if (!$this->program_id) {
            return false;
        }

        static $programIssuedCache = [];
        $cacheKey = implode('|', [
            strtolower((string) $this->method),
            $this->program_id ?? '',
            $this->bank_account_id ?? '',
            $this->transaction_id ?? '',
            $this->amount ?? '',
            optional($this->date)->format('Y-m-d') ?? '',
        ]);

        if (!array_key_exists($cacheKey, $programIssuedCache)) {
            $programIssuedCache[$cacheKey] = (bool) $this->findMatchingProgramSupplierPayment();
        }

        return $programIssuedCache[$cacheKey];
    }

    protected static function whereMeaningfulTransactionMatches($query): void
    {
        $query->whereColumn('supplier_payments.transaction_id', 'customer_payments.transaction_id');
        self::whereCustomerTransactionIsMeaningful($query);
        self::whereSupplierTransactionIsMeaningful($query);
    }

    protected static function whereCustomerTransactionIsMeaningful($query): void
    {
        $query->whereNotNull('customer_payments.transaction_id')
            ->where('customer_payments.transaction_id', '!=', '')
            ->where('customer_payments.transaction_id', '!=', '0');
    }

    protected static function whereSupplierTransactionIsMeaningful($query): void
    {
        $query->whereNotNull('supplier_payments.transaction_id')
            ->where('supplier_payments.transaction_id', '!=', '')
            ->where('supplier_payments.transaction_id', '!=', '0');
    }

    protected static function whereCustomerTransactionIsNotMeaningful($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('customer_payments.transaction_id')
                ->orWhere('customer_payments.transaction_id', '')
                ->orWhere('customer_payments.transaction_id', '0');
        });
    }

    protected static function whereSupplierTransactionIsNotMeaningful($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('supplier_payments.transaction_id')
                ->orWhere('supplier_payments.transaction_id', '')
                ->orWhere('supplier_payments.transaction_id', '0');
        });
    }

    protected function dataForResponse()
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'customer_name' => $this->customer->customer_name,
                'city' => $this->customer->city ? [
                    'id' => $this->customer->city->id,
                    'title' => $this->customer->city->title,
                    'short_title' => $this->customer->city->short_title,
                ] : null,
            ] : null,
            'date' => optional($this->date)->toJSON(),
            'method' => $this->method,
            'type' => $this->type,
            'amount' => $this->amount,
            'cheque_no' => $this->cheque_no,
            'slip_no' => $this->slip_no,
            'transaction_id' => $this->transaction_id,
            'reff_no' => $this->reff_no,
            'cheque_date' => optional($this->cheque_date)->toJSON(),
            'slip_date' => optional($this->slip_date)->toJSON(),
            'clear_date' => optional($this->clear_date)->toJSON(),
            'remarks' => $this->remarks,
            'bank' => $this->bank?->title ?? $this->bank?->short_title ?? null,
            'bank_account' => $this->formatBankAccountForResponse($this->bankAccount),
            'cheque' => $this->formatSupplierPaymentForResponse($this->cheque),
            'slip' => $this->formatSupplierPaymentForResponse($this->slip),
            'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('customer_payments', $this),
        ];
    }

    protected function formatSupplierPaymentForResponse($payment): ?array
    {
        if (!$payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'supplier_id' => $payment->supplier_id,
            'voucher' => $payment->voucher ? [
                'id' => $payment->voucher->id,
                'voucher_no' => $payment->voucher->voucher_no,
                'supplier' => $payment->voucher->supplier ? [
                    'id' => $payment->voucher->supplier->id,
                    'supplier_name' => $payment->voucher->supplier->supplier_name,
                    'bank_accounts' => $payment->voucher->supplier->bankAccounts
                        ?->map(fn ($account) => $this->formatBankAccountForResponse($account))
                        ->values()
                        ->all() ?? [],
                ] : null,
            ] : null,
            'cr' => $payment->cr ? [
                'id' => $payment->cr->id,
                'c_r_no' => $payment->cr->c_r_no,
                'voucher' => $payment->cr->voucher ? [
                    'id' => $payment->cr->voucher->id,
                    'voucher_no' => $payment->cr->voucher->voucher_no,
                    'supplier' => $payment->cr->voucher->supplier ? [
                        'id' => $payment->cr->voucher->supplier->id,
                        'supplier_name' => $payment->cr->voucher->supplier->supplier_name,
                        'bank_accounts' => $payment->cr->voucher->supplier->bankAccounts
                            ?->map(fn ($account) => $this->formatBankAccountForResponse($account))
                            ->values()
                            ->all() ?? [],
                    ] : null,
                ] : null,
            ] : null,
        ];
    }

    protected function formatBankAccountForResponse($account): ?array
    {
        if (!$account) {
            return null;
        }

        return [
            'id' => $account->id,
            'account_title' => $account->account_title,
            'bank' => $account->bank ? [
                'id' => $account->bank->id,
                'title' => $account->bank->title,
                'short_title' => $account->bank->short_title,
            ] : null,
        ];
    }

    protected function formatClearRecord($record)
    {
        $amount = (float) ($record?->amount ?? 0);

        return [
            'date' => $record?->clear_date?->format('d-M-Y, D') ?? '-',
            'method' => $record?->method ? ucfirst($record->method) : '-',
            'account_title' => $record?->bankAccount?->account_title ?? '-',
            'bank' => $record?->bankAccount?->bank?->short_title ?? '-',
            'amount' => \App\Support\Money::format($amount),
            'amount_numeric' => $amount,
            'reff_no' => $record?->reff_no ?? '-',
            'remarks' => $record?->remarks ?: '-',
        ];
    }

    /**
     * Voucher number computation
     */
    public function voucherNo(): Attribute
    {
        return Attribute::get(function () {
            static $voucherNoCache = [];

            // direct relations
            if ($this->cheque?->voucher) return $this->cheque->voucher->voucher_no;
            if ($this->slip?->voucher)   return $this->slip->voucher->voucher_no;
            if ($this->cheque?->cr)      return $this->cheque->cr->c_r_no;
            if ($this->slip?->cr)        return $this->slip->cr->c_r_no;
            if ($this->returnedCheque?->cr) return $this->returnedCheque->cr->c_r_no;
            if ($this->returnedSlip?->cr)   return $this->returnedSlip->cr->c_r_no;

            // program-based fallback
            if ($this->isProgramMethod() && $this->program_id) {
                $cacheKey = implode('|', [
                    strtolower((string) $this->method),
                    $this->program_id ?? '',
                    $this->bank_account_id ?? '',
                    $this->transaction_id ?? '',
                    $this->amount ?? '',
                    optional($this->date)->format('Y-m-d') ?? '',
                ]);

                if (array_key_exists($cacheKey, $voucherNoCache)) {
                    return $voucherNoCache[$cacheKey];
                }

                $supplierPayment = $this->findMatchingProgramSupplierPayment();

                $voucherNoCache[$cacheKey] = $supplierPayment?->voucher?->voucher_no
                    ?? $supplierPayment?->cr?->c_r_no
                    ?? $supplierPayment?->linkedCrReference()?->c_r_no
                    ?? null;
                return $voucherNoCache[$cacheKey];
            }

            if ($this->issued == 'Return') return 'Return';

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

            // if ($this->cheque?->supplier) return $this->cheque->supplier->supplier_name;
            // if ($this->slip?->supplier)   return $this->slip->supplier->supplier_name;
            if ($this->bankAccount)   return $this->bankAccount?->account_title . ' | ' . $this->bankAccount?->bank->short_title;
            // if ($this->cheque?->voucher?->supplier) return $this->cheque?->voucher?->supplier->supplier_name;
            // if ($this->slip?->voucher?->supplier)   return $this->slip?->voucher?->supplier->supplier_name;
            // if ($this->bankAccount?->subCategory)   return $this->bankAccount?->subCategory->supplier_name;

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
                // Prefer direct clear_date (set only when fully cleared)
                if ($this->clear_date) {
                    return $this->clear_date->format('d-M-Y, D');
                }

                // Otherwise check payment clear records
                $clearedAmount = $this->paymentClearRecord->sum('amount');
                if ($clearedAmount >= $this->amount) {
                    $last = $this->paymentClearRecord
                        ->sortByDesc('clear_date')
                        ->first();
                    if ($last?->clear_date) {
                        return $last->clear_date->format('d-M-Y, D');
                    }
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
                $sum = $this->paymentClearRecord->sum('amount');
                if ($sum > 0) {
                    return $sum;
                }
                if ($this->clear_date !== null) {
                    return $this->amount;
                }
                return 0;
            }
            return null;
        });
    }

    public function category(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->customer) {
                return null;
            }

            return $this->customer->category == 'cash' ? 'cash' : 'non-cash';
        });
    }

    public function issued(): Attribute
    {
        return Attribute::get(function () {
            $method = strtolower((string) $this->method);

            if ($this->d_r_id !== null) {
                return 'DR';
            }

            if (in_array($method, ['cash', 'adjustment'], true)) {
                return null;
            }

            if ($this->is_return && $this->d_r_id === null) {
                return 'Return';
            }

            if ($method === 'program') {
                if (request()->filled('issued')) {
                    $issuedValue = strtolower(trim((string) request('issued')));
                    $issuedValue = str_replace('_', ' ', explode(':', $issuedValue, 2)[0]);

                    if ($issuedValue === 'issued') {
                        return 'Issued';
                    }

                    if ($issuedValue === 'not issued') {
                        return 'Not Issued';
                    }
                }

                return $this->hasProgramVoucher() ? 'Issued' : 'Not Issued';
            }

            if (($this->cheque || $this->slip) && !$this->is_return) {
                return 'Issued';
            }

            return 'Not Issued';
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
            static $maxSuffixCache = [];

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

            $cacheKey = $column . '|' . $baseRef;
            if (array_key_exists($cacheKey, $maxSuffixCache)) {
                return $maxSuffixCache[$cacheKey];
            }

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

            $maxSuffixCache[$cacheKey] = $max;
            return $maxSuffixCache[$cacheKey];
        });
    }

    public function toFormattedArray()
    {
        $clearDetails = $this->paymentClearRecord
            ? $this->paymentClearRecord
                ->sortBy('clear_date')
                ->map(fn($record) => $this->formatClearRecord($record))
                ->values()
            : collect();

        $amount = (float) ($this->amount ?? 0);
        $clearedAmount = $this->cleared_amount;
        $clearedAmountNumeric = is_null($clearedAmount) ? null : (float) $clearedAmount;

        return [
            'id' => $this->id,
            'name' => ($this->customer?->customer_name ?? '-') . ' | ' . ($this->customer?->city?->title ?? '-'),
            'details' => [
                'Type' => $this->type,
                'Method' => $this->method,
                'Date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
                'Amount' => \App\Support\Money::format($amount),
            ],
            'amount_numeric' => $amount,
            'method' => $this->method,
            'data' => $this->dataForResponse(),
            'date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
            'program_date' => $this->program?->date ? $this->program?->date->format('d-M-Y, D') : null,
            'voucher_no' => $this->voucher_no ?? '-',
            'cr_no' => $this->cheque?->cr?->c_r_no
                ?? $this->slip?->cr?->c_r_no
                ?? $this->returnedCheque?->cr?->c_r_no
                ?? $this->returnedSlip?->cr?->c_r_no
                ?? null,
            'supplier_name' => $this->supplier_name,
            'reff_no' => $this->reff_no,
            'beneficiary' => $this->beneficiary,
            'clear_date' => $this->clearance_date,
            'cleared_amount' => is_null($clearedAmountNumeric) ? null : \App\Support\Money::format($clearedAmountNumeric),
            'cleared_amount_numeric' => $clearedAmountNumeric,
            'clear_details' => $clearDetails,
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
                    ->orWhereHas('returnedCheque.cr', fn ($sq) =>
                            $sq->where('c_r_no', 'like', "%$value%")
                        )
                    ->orWhereHas('returnedSlip.cr', fn ($sq) =>
                            $sq->where('c_r_no', 'like', "%$value%")
                        )

                    // Program based supplier voucher/CR reference.
                    ->orWhereExists(function ($sq) use ($value) {
                            $sq->selectRaw(1)
                                ->from('supplier_payments')
                                ->join('vouchers', 'vouchers.id', '=', 'supplier_payments.voucher_id')
                                ->whereRaw('LOWER(customer_payments.method) = ?', ['program'])
                                ->where('vouchers.voucher_no', 'like', "%$value%");

                            self::addMatchingProgramSupplierPaymentConstraints($sq);
                        })
                    ->orWhereExists(function ($sq) use ($value) {
                            $sq->selectRaw(1)
                                ->from('supplier_payments')
                                ->join('c_r_s', 'c_r_s.id', '=', 'supplier_payments.c_r_id')
                                ->whereRaw('LOWER(customer_payments.method) = ?', ['program'])
                                ->where('c_r_s.c_r_no', 'like', "%$value%");

                            self::addMatchingProgramSupplierPaymentConstraints($sq);
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
                    ->orWhereHas('slip.voucher.supplier', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"))
                    ->orWhereHas('program.subCategory', fn($sq) => $sq->where('supplier_name', 'like', "%$value%"));
                });

            case 'reff_no':
                return $query->where(function($q) use ($value) {
                    $q->where('cheque_no', 'like', "%$value%")
                    ->orWhere('slip_no', 'like', "%$value%")
                    ->orWhere('transaction_id', 'like', "%$value%")
                    ->orWhere('reff_no', 'like', "%$value%")
                    ->orWhereHas('paymentClearRecord', fn ($sq) => $sq->where('reff_no', 'like', "%{$value}%"));
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
                $issuedValueRaw = strtolower(trim((string) $value));
                // Support "Issued:1" or any suffix from UI/state
                $issuedValue = explode(':', $issuedValueRaw, 2)[0];
                return $query->where(function($q) use ($issuedValue) {
                    if ($issuedValue === 'issued') {
                        $q->where('is_return', 0)
                          ->whereNull('d_r_id')
                          ->whereRaw('LOWER(method) NOT IN (?, ?)', ['cash', 'adjustment'])
                          ->where(function($sq) {
                              $sq->where(function ($methodQ) {
                                  $methodQ->whereRaw('LOWER(customer_payments.method) != ?', ['program'])
                                      ->where(function ($nonProgramQ) {
                                          $nonProgramQ->whereExists(function ($sub) {
                                              $sub->selectRaw(1)
                                                  ->from('supplier_payments')
                                                  ->whereColumn('supplier_payments.cheque_id', 'customer_payments.id');
                                          })
                                          ->orWhereExists(function ($sub) {
                                              $sub->selectRaw(1)
                                                  ->from('supplier_payments')
                                                  ->whereColumn('supplier_payments.slip_id', 'customer_payments.id');
                                          });
                                      });
                              })
                              ->orWhere(function ($programQ) {
                                  $programQ->whereRaw('LOWER(customer_payments.method) = ?', ['program'])
                                      ->whereExists(function ($sub) {
                                          $sub->selectRaw(1)->from('supplier_payments');
                                          self::addMatchingProgramSupplierPaymentConstraints($sub);
                                          $sub->where(function ($sourceQ) {
                                              $sourceQ->whereNotNull('supplier_payments.voucher_id')
                                                  ->orWhereNotNull('supplier_payments.c_r_id')
                                                  ->orWhereRaw('LOWER(supplier_payments.method) LIKE ?', ['%cr%']);
                                          });
                                      });
                              });
                          });
                    }
                    elseif ($issuedValue === 'return') {
                        $q->where('is_return', 1)
                          ->whereNull('d_r_id')
                          ->whereRaw('LOWER(method) NOT IN (?, ?)', ['cash', 'adjustment']);
                    }
                    elseif ($issuedValue === 'dr') {
                        $q->whereNotNull('d_r_id')
                          ->whereRaw('LOWER(method) NOT IN (?, ?)', ['cash', 'adjustment']);
                    }
                    elseif ($issuedValue === 'not issued' || $issuedValue === 'not_issued') {
                        $q->where('is_return', 0)
                          ->whereNull('d_r_id')
                          ->whereRaw('LOWER(method) NOT IN (?, ?)', ['cash', 'adjustment'])
                          ->where(function($sq) {
                              $sq->where(function ($methodQ) {
                                  $methodQ->whereRaw('LOWER(customer_payments.method) != ?', ['program'])
                                      ->whereNotExists(function ($sub) {
                                          $sub->selectRaw(1)
                                              ->from('supplier_payments')
                                              ->whereColumn('supplier_payments.cheque_id', 'customer_payments.id');
                                      })
                                      ->whereNotExists(function ($sub) {
                                          $sub->selectRaw(1)
                                              ->from('supplier_payments')
                                              ->whereColumn('supplier_payments.slip_id', 'customer_payments.id');
                                      });
                              })
                              ->orWhere(function ($programQ) {
                                  $programQ->whereRaw('LOWER(customer_payments.method) = ?', ['program'])
                                      ->whereNotExists(function ($sub) {
                                          $sub->selectRaw(1)->from('supplier_payments');
                                          self::addMatchingProgramSupplierPaymentConstraints($sub);
                                          $sub->where(function ($sourceQ) {
                                              $sourceQ->whereNotNull('supplier_payments.voucher_id')
                                                  ->orWhereNotNull('supplier_payments.c_r_id')
                                                  ->orWhereRaw('LOWER(supplier_payments.method) LIKE ?', ['%cr%']);
                                          });
                                      });
                              });
                          });
                    }
                    else {
                        // Unknown filter value should not return all records
                        $q->whereRaw('1=0');
                    }
                });

            case 'category':
                return $query->whereHas('customer', function($q) use ($value) {
                    if ($value == 'cash') $q->where('category', 'cash');
                    else $q->where('category', '!=', 'cash');
                });

            case 'method':
                return $query->whereRaw('LOWER(method) = ?', [strtolower((string) $value)]);

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;


                return $query->where(function ($q) use ($start, $end) {
                    // 1️⃣ slip_date exists
                    $q->where(function ($q) use ($start, $end) {
                        $q->whereNotNull('slip_date')
                        ->whereDate('slip_date', '>=', $start)
                        ->whereDate('slip_date', '<=', $end);
                    })
                    // 2️⃣ slip_date null, cheque_date exists
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('slip_date')
                        ->whereNotNull('cheque_date')
                        ->whereDate('cheque_date', '>=', $start)
                        ->whereDate('cheque_date', '<=', $end);
                    })
                    // 3️⃣ slip_date null, cheque_date null, fallback to date
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('slip_date')
                        ->whereNull('cheque_date')
                        ->whereDate('date', '>=', $start)
                        ->whereDate('date', '<=', $end);
                    });
                });

            case 'created_at':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;

                return $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse($start)->startOfDay(),
                    \Carbon\Carbon::parse($end)->endOfDay(),
                ]);

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
