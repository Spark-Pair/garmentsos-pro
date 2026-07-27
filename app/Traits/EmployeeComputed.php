<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait EmployeeComputed
{
    public function toFormattedArray()
    {
        $displayBalance = $this->displayBalanceForEmployeePage();

        return [
            'id' => $this->id,
            'uId' => $this->id,
            'status' => $this->status,
            'image' => $this->profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : '/storage/uploads/images/' . $this->profile_picture,
            'name' => $this->employee_name,
            'urdu_title' => $this->urdu_title,
            'phone_number' => $this->phone_number,
            'details' => [
                'Category'=> $this->category,
                'Type'=> $this->type->title,
                'Balance'=> \App\Support\Money::format($displayBalance),
            ],
            'type' => $this->type->title,
            'joining_date' => $this->joining_date->format('d-M-Y, D'),
            'cnic_no' => $this->cnic_no,
            'salary' => $this->salary,
            'profile' => true,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    private function displayBalanceForEmployeePage(): float|int
    {
        if (!request()->routeIs('employees.index')) {
            return $this->balance;
        }

        try {
            $branches = app(ModuleBranchService::class);
            if (!$branches->canShowSelector('employees') && !$branches->shouldFilterRecords('employees')) {
                return $this->balance;
            }

            $branchIds = $branches->selectedBranchIdsForModule('employees');
            if ($branchIds === [] && $branches->shouldFilterRecords('employees')) {
                $branchIds = array_values(array_filter([
                    $branches->selectedBranchIdForModule('employees'),
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
            case 'type':
                return $query->where('type_id', $value);

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
