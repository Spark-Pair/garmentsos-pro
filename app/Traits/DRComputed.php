<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait DRComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'd_r_no' => $this->d_r_no,
            'customer_name' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->where(function ($query) use ($value) {
                    $query->whereHas('customer', function ($q) use ($value) {
                        $q->where('customer_name', 'like', "%{$value}%")
                        ->orWhereHas('city', function ($q) use ($value) {
                            $q->where('title', 'like', "%{$value}%");
                        });
                    });
                });

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
