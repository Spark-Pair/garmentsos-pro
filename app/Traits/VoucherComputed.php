<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait VoucherComputed
{
    public function toFormattedArray()
    {
        static $balanceCache = [];
        $previousBalance = 0;
        if ($this->supplier && $this->date) {
            $scope = $this->voucherBalanceBranchScope();
            $cacheKey = implode('|', [
                $this->supplier->id,
                $this->date->format('Y-m-d'),
                implode(',', $scope['branch_ids']),
                $scope['include_null_branch_records'] ? 'null' : 'strict',
            ]);

            if (!array_key_exists($cacheKey, $balanceCache)) {
                $balanceCache[$cacheKey] = $this->supplier->calculateBalance(
                    null,
                    $this->date,
                    false,
                    true,
                    $scope['branch_ids'],
                    $scope['include_null_branch_records'],
                );
            }
            $previousBalance = $balanceCache[$cacheKey];
        }

        $payments = $this->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'method' => $payment->method,
                'date' => $payment->date?->format('Y-m-d'),
                'amount' => (float) $payment->amount,
                'voucher_no' => $this->voucher_no,
                'cheque_no' => $payment->cheque_no ?? $payment->cheque?->cheque_no,
                'reff_no' => $payment->reff_no,
                'transaction_id' => $payment->transaction_id,
                'remarks' => $payment->remarks,
                'program' => $payment->program ? [
                    'id' => $payment->program->id,
                    'customer' => $payment->program->customer ? [
                        'id' => $payment->program->customer->id,
                        'customer_name' => $payment->program->customer->customer_name,
                    ] : null,
                ] : null,
                'cheque' => $payment->cheque ? [
                    'id' => $payment->cheque->id,
                    'cheque_no' => $payment->cheque->cheque_no,
                    'customer' => $payment->cheque->customer ? [
                        'id' => $payment->cheque->customer->id,
                        'customer_name' => $payment->cheque->customer->customer_name,
                    ] : null,
                ] : null,
                'slip' => $payment->slip ? [
                    'id' => $payment->slip->id,
                    'slip_no' => $payment->slip->slip_no,
                    'customer' => $payment->slip->customer ? [
                        'id' => $payment->slip->customer->id,
                        'customer_name' => $payment->slip->customer->customer_name,
                    ] : null,
                ] : null,
                'bank_account' => $payment->bankAccount ? [
                    'id' => $payment->bankAccount->id,
                    'account_title' => $payment->bankAccount->account_title,
                    'display_label' => $this->voucherAccountLabel($payment->bankAccount->account_title),
                    'bank' => $payment->bankAccount->bank ? [
                        'id' => $payment->bankAccount->bank->id,
                        'short_title' => $payment->bankAccount->bank->short_title,
                    ] : null,
                ] : null,
                'self_account' => $payment->selfAccount ? [
                    'id' => $payment->selfAccount->id,
                    'account_title' => $payment->selfAccount->account_title,
                    'display_label' => $this->voucherAccountLabel($payment->selfAccount->account_title),
                    'bank' => $payment->selfAccount->bank ? [
                        'id' => $payment->selfAccount->bank->id,
                        'short_title' => $payment->selfAccount->bank->short_title,
                    ] : null,
                ] : null,
            ];
        })->values();

        return [
            'id' => $this->id,
            'name' => $this->voucher_no,
            'details' => [
                'Supplier' => $this->supplier ? $this->supplier->supplier_name : app('client_company')->name,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount' => \App\Support\Money::format($this->payments->sum('amount')),
            ],
            'total_payment' => \App\Support\Money::format($this->payments->sum('amount')),
            'total_payment_numeric' => (float) $this->payments->sum('amount'),
            'previous_balance' => \App\Support\Money::format($previousBalance),
            'previous_balance_numeric' => (float) $previousBalance,
            'data' => [
                'id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('vouchers', $this),
                'date' => $this->date?->format('Y-m-d'),
                'voucher_no' => $this->voucher_no,
                'supplier' => $this->supplier ? [
                    'id' => $this->supplier->id,
                    'supplier_name' => $this->supplier->supplier_name,
                ] : null,
                'payments' => $payments,
                'created_at' => $this->created_at?->format('Y-m-d, h:i A'),
                'updated_at' => $this->updated_at?->format('Y-m-d, h:i A'),
                'creator' => $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    private function voucherBalanceBranchScope(): array
    {
        try {
            $branches = app(ModuleBranchService::class);

            if (!$branches->shouldFilterRecords('vouchers')) {
                return [
                    'branch_ids' => [],
                    'include_null_branch_records' => false,
                ];
            }

            $branchIds = collect($branches->selectedBranchIdsForModule('vouchers') ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($branchIds === []) {
                $selectedBranchId = $branches->selectedBranchIdForModule('vouchers');
                if ($selectedBranchId) {
                    $branchIds = [(int) $selectedBranchId];
                }
            }

            $mainBranchId = $branches->mainBranch()?->id;

            return [
                'branch_ids' => $branchIds,
                'include_null_branch_records' => (bool) (
                    $mainBranchId && in_array((int) $mainBranchId, $branchIds, true)
                ),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'branch_ids' => [],
                'include_null_branch_records' => false,
            ];
        }
    }

    private function voucherAccountLabel(?string $accountTitle): string
    {
        $parts = collect(explode('|', (string) $accountTitle))
            ->map(fn ($part) => trim($part))
            ->filter();

        return (string) ($parts->last() ?: $accountTitle ?: '-');
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->where(function ($query) use ($value) {

                    // Case 1: supplier exists → supplier_name
                    $query->whereHas('supplier', function ($q) use ($value) {
                        $q->where('supplier_name', 'like', "%{$value}%");
                    })

                    // Case 2: supplier does NOT exist → fallback to client_company name
                    ->orWhere(function ($q) use ($value) {
                        $q->whereDoesntHave('supplier')
                        ->whereRaw('? LIKE ?', [app('client_company')->name, "%{$value}%"]);
                    });

                });

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;

                \App\Support\DateRange::apply($query, 'date', $start, $end);
                return $query;

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
