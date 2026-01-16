<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait BankAccountComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'uId' => $this->id,
            'status' => $this->status,
            'name' => $this->account_title,
            'details' => [
                'Name'=> $this->subCategory->customer_name ?? $this->subCategory->supplier_name ?? $this->account_title,
                'Category'=> $this->category,
                'Balance'=> number_format($this->balance),
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

                if (!$start || !$end) return $query->where('method', 'cash');


                return $query->where(function ($q) use ($start, $end) {
                    // 1️⃣ slip_date exists
                    $q->Where(function ($q) use ($start, $end) {
                        $q->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
                    });
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
