<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait UtilityAccountComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'bill_type' => $this->billType->title,
            'location' => $this->location->title,
            'account_title' => $this->account_title,
            'account_no' => $this->account_no,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'bill_type':
                return $query->whereHas('billType', function ($q) use ($value) {
                    $q->where('title', 'like', "%{$value}%");
                });

            case 'location':
                return $query->whereHas('location', function ($q) use ($value) {
                    $q->where('title', 'like', "%{$value}%");
                });

            // case 'date':
            //     $start = $value['start'] ?? null;
            //     $end   = $value['end'] ?? null;

            //     if (!$start || !$end) return $query->where('method', 'cash');


            //     return $query->where(function ($q) use ($start, $end) {
            //         // 1️⃣ slip_date exists
            //         $q->Where(function ($q) use ($start, $end) {
            //             $q->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
            //         });
            //     });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
