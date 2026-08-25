<?php

namespace App\Traits;

use App\Services\Branches\ModuleBranchService;
use App\Services\Orders\OrderBalanceService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait PaymentProgramComputed
{
    public function beneficiary(): Attribute
    {
        return Attribute::get(function () {
            if ($this->category === 'supplier') {
                return $this->subCategory?->supplier_name ?? '-';
            }

            if ($this->category === 'self_account') {
                return $this->subCategory?->account_title ?? '-';
            }

            if ($this->category === 'waiting') {
                return $this->remarks ?? '-';
            }

            return '-';
        });
    }

    /**
     * Get selected Payment Programs branches.
     *
     * Customer balance bhi inhi selected branches ke records
     * se calculate hoga.
     */
    private function paymentProgramBranchScope(): array
    {
        try {
            $branches = app(ModuleBranchService::class);

            $branchIds = $branches->selectedBranchIdsForModule(
                'payment_programs'
            );

            $branchIds = collect($branchIds ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            /*
             * Jab module single/default branch filtering use kar raha ho
             * aur selectedBranchIdsForModule empty return kare.
             */
            if (
                $branchIds === [] &&
                $branches->shouldFilterRecords('payment_programs')
            ) {
                $selectedBranchId = $branches
                    ->selectedBranchIdForModule('payment_programs');

                if ($selectedBranchId) {
                    $branchIds = [(int) $selectedBranchId];
                }
            }

            /*
             * Purane records jin ka branch_id NULL hai unhein main branch
             * ke saath include karna hai.
             */
            $mainBranchId = $branches->mainBranch()?->id;

            $includeNullBranchRecords = $mainBranchId
                && in_array(
                    (int) $mainBranchId,
                    array_map('intval', $branchIds),
                    true
                );

            return [
                'branch_ids' => $branchIds,
                'include_null_branch_records' => (bool) $includeNullBranchRecords,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'branch_ids' => [],
                'include_null_branch_records' => false,
            ];
        }
    }

    /**
     * Selected Payment Programs branch ke mutabiq
     * customer ka ledger balance.
     */
    private function branchWiseCustomerBalance(): float
    {
        if (!$this->customer) {
            return 0;
        }

        $scope = $this->paymentProgramBranchScope();

        /*
         * Empty branch scope ka matlab all branches hai.
         */
        return (float) $this->customer->calculateBalance(
            branchIds: $scope['branch_ids'],
            includeNullBranchRecords: $scope['include_null_branch_records'],
        );
    }

    public function toFormattedArray()
    {
        $paymentRows = $this->customerPayments
            ->map(function ($payment) {
                $bankAccount = $payment->bankAccount;

                return [
                    'id' => $payment->id,
                    'date' => $payment->date->format('Y-m-d'),
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'type' => $payment->type,
                    'transaction_id' => $payment->transaction_id,
                    'cheque_no' => $payment->cheque_no,
                    'cheque_date' => $payment->cheque_date,
                    'slip_no' => $payment->slip_no,
                    'slip_date' => $payment->slip_date,
                    'reff_no' => $payment->reff_no,
                    'clear_date' => $payment->clear_date,
                    'remarks' => $payment->remarks,

                    'bank_account' => $bankAccount ? [
                        'id' => $bankAccount->id,
                        'account_title' => $bankAccount->account_title,

                        'bank' => $bankAccount->bank ? [
                            'id' => $bankAccount->bank->id,
                            'short_title' => $bankAccount->bank->short_title,
                            'title' => $bankAccount->bank->title,
                        ] : null,

                        'sub_category' => $bankAccount->subCategory ? [
                            'id' => $bankAccount->subCategory->id,
                            'supplier_name' =>
                                $bankAccount->subCategory->supplier_name ?? null,
                            'customer_name' =>
                                $bankAccount->subCategory->customer_name ?? null,
                        ] : null,
                    ] : null,
                ];
            })
            ->values();

        $customerBalance = $this->branchWiseCustomerBalance();
        $scope = $this->paymentProgramBranchScope();
        $orderBalance = app(OrderBalanceService::class)->pendingForCustomer(
            $this->customer,
            $scope['branch_ids'],
            $scope['include_null_branch_records'],
        );

        return [
            'id' => $this->id,
            'date' => $this->date?->format('d-M-Y, D'),

            'customer_name' =>
                ($this->customer?->customer_name ?? '-')
                . ' | '
                . ($this->customer?->city?->title ?? '-'),

            /*
             * Selected Payment Programs branch ka customer balance.
             */
            'customer_balance' => $customerBalance,

            'o_p_no' => $this->order_no ?? $this->program_no,
            'category' => $this->category,
            'beneficiary' => $this->beneficiary,

            'amount' => (float) $this->amount,
            'payment' => (float) $this->payment,
            'balance' => (float) $this->balance,

            /*
             * Customer ke selected branch scope ka total pending order balance.
             */
            'order_balance' => $orderBalance,

            'status' => $this->status,
            'type' => $this->order_no || $this->order_id
                ? 'order'
                : 'program',

            'sub_category' => $this->subCategory ? [
                'id' => $this->subCategory->id,
            ] : null,

            'data' => [
                'id' => $this->id,
                'sub_category_id' => $this->sub_category_id,
                'sub_category_type' => $this->sub_category_type,
                'payments' => $paymentRows,

                /*
                 * Modal mein bhi available hoga.
                 */
                'customer_balance' => $customerBalance,
                'order_balance' => $orderBalance,
            ],

            'oncontextmenu' => 'generateContextMenu(event)',
            'onclick' => 'generateModal(this)',
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->whereHas(
                    'customer',
                    function ($q) use ($value) {
                        $q->where(
                            'customer_name',
                            'like',
                            "%{$value}%"
                        )->orWhereHas(
                            'city',
                            fn ($sq) => $sq->where(
                                'title',
                                'like',
                                "%{$value}%"
                            )
                        );
                    }
                );

            case 'city':
                return $query->whereHas(
                    'customer.city',
                    function ($q) use ($value) {
                        $q->where(function ($sq) use ($value) {
                            $sq->where(
                                'title',
                                'like',
                                "%{$value}%"
                            )->orWhere(
                                'short_title',
                                'like',
                                "%{$value}%"
                            );
                        });
                    }
                );

            case 'type':
                return $query->where(function ($q) use ($value) {
                    if ($value === 'order') {
                        $q->whereNotNull('order_no');
                    } else {
                        $q->whereNull('order_no');
                    }
                });

            case 'beneficiary':
                return $query->where(function ($q) use ($value) {
                    $q->whereHas(
                        'subCategory',
                        function ($sq) use ($value) {
                            $sq->where(
                                'supplier_name',
                                'like',
                                "%{$value}%"
                            )->orWhere(
                                'account_title',
                                'like',
                                "%{$value}%"
                            );
                        }
                    )->orWhere(function ($q) use ($value) {
                        $q->whereDoesntHave('subCategory')
                            ->where(
                                'remarks',
                                'like',
                                "%{$value}%"
                            );
                    });
                });

            case 'status':
                return $query->where('status', $value);

            case 'date':
                $start = $value['start'] ?? null;
                $end = $value['end'] ?? null;

                if (!$start || !$end) {
                    return $query;
                }

                \App\Support\DateRange::apply(
                    $query,
                    'date',
                    $start,
                    $end
                );

                return $query;

            default:
                return $query->where(
                    $key,
                    'like',
                    "%{$value}%"
                );
        }
    }
}
