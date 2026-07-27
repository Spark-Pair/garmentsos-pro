<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait BankAccountComputed
{
    public function toFormattedArray()
    {
        $displayBalance = $this->displayBalanceForBankAccountPage();

        return [
            'id' => $this->id,
            'uId' => $this->id,
            'status' => $this->status,
            'name' => $this->account_title,
            'details' => [
                'Name'=> $this->subCategory->customer_name ?? $this->subCategory->supplier_name ?? $this->account_title,
                'Category'=> $this->category,
                'Balance'=> \App\Support\Money::format($displayBalance),
            ],
            'account_no' => $this->account_no ?? 0,
            'bank' => $this->bank->title,
            'date' => $this->date->format('d-M-Y, D'),
            'chqbkSerialStart' => $this->chqbk_serial_start ?? 0,
            'chqbkSerialEnd' => $this->chqbk_serial_end ?? 0,
            'available_cheques' => $this->available_cheques ?? [],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    private function displayBalanceForBankAccountPage(): float|int
    {
        if (!request()->routeIs('bank-accounts.index')) {
            return $this->balance;
        }

        try {
            $branches = app(ModuleBranchService::class);
            if (!$branches->canShowSelector('bank_accounts') && !$branches->shouldFilterRecords('bank_accounts')) {
                return $this->balance;
            }

            $branchIds = $branches->selectedBranchIdsForModule('bank_accounts');
            if ($branchIds === [] && $branches->shouldFilterRecords('bank_accounts')) {
                $branchIds = array_values(array_filter([
                    $branches->selectedBranchIdForModule('bank_accounts'),
                ]));
            }

            if ($branchIds === []) {
                return $this->balance;
            }

            $mainBranchId = $branches->mainBranch()?->id;
            $includeNullBranchRecords = $mainBranchId
                && in_array((int) $mainBranchId, array_map('intval', $branchIds), true);

            return $this->calculateBalance(
                branchIds: $branchIds,
                includeNullBranchRecords: (bool) $includeNullBranchRecords,
            );
        } catch (\Throwable) {
            return $this->balance;
        }
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'name':
                return $query->where(function ($main) use ($value) {

                    // 1️⃣ If subCategory exists → search there
                    $main->whereHas('subCategory', function ($q) use ($value) {
                        $q->where('customer_name', 'like', "%{$value}%")
                        ->orWhere('supplier_name', 'like', "%{$value}%");
                    })

                    // 2️⃣ If subCategory does NOT exist → fallback to account_title
                    ->orWhere(function ($fallback) use ($value) {
                        $fallback->where('account_title', 'like', "%{$value}%");
                    });

                });

            case 'bank':
                return $query->where('bank_id', $value);

            case 'status':
                return $query->where('status', $value);

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
