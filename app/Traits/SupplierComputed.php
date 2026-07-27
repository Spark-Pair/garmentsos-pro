<?php

namespace App\Traits;

use App\Models\Setup;
use App\Models\SupplierPayment;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SupplierComputed
{
    public function toFormattedArray()
    {
        $displayBalance = $this->displayBalanceForSupplierPage();

        return [
            'id' => $this->id,
            'image' => $this->user->profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : '/storage/uploads/images/' . $this->user->profile_picture,
            'name' => $this->supplier_name,
            'details' => [
                'Urdu Title' => $this->urdu_title,
                'Phone' => $this->phone_number,
                'Balance' => \App\Support\Money::format($displayBalance),
            ],
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'status' => $this->user->status,
            ],
            'date' => $this->date?->format('Y-m-d'),
            'data'=> [
                'id' => $this->id,
                'supplier_name' => $this->supplier_name,
                'person_name' => $this->person_name,
                'urdu_title' => $this->urdu_title,
                'phone_number' => $this->phone_number,
                'date' => $this->date?->format('Y-m-d'),
                'categories_array' => $this->categories_array,
                'user' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'status' => $this->user->status,
                ],
            ],
            'categories'=> $this->Categories,
            'profile'=> true,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    private function displayBalanceForSupplierPage(): float|int
    {
        if (!request()->routeIs('suppliers.index')) {
            return $this->balance;
        }

        try {
            $branches = app(ModuleBranchService::class);
            if (!$branches->canShowSelector('suppliers') && !$branches->shouldFilterRecords('suppliers')) {
                return $this->balance;
            }

            $branchIds = $branches->selectedBranchIdsForModule('suppliers');
            if ($branchIds === [] && $branches->shouldFilterRecords('suppliers')) {
                $branchIds = array_values(array_filter([
                    $branches->selectedBranchIdForModule('suppliers'),
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
            case 'username':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('username', 'like', '%' . $value . '%');
                });

            case 'category':
                $categoryId = Setup::where('id', 'like', '%' . $value . '%')
                                    ->value('id');

                if ($categoryId) {
                    return $query->whereJsonContains('categories_array', $categoryId);
                }

                return $query;

            case 'status':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('status', 'like', '%' . $value . '%');
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
