<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait DailyLedgerDepositComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'description' => ucfirst($this->method) . ' | ' . ($this->reff_no ?? '-'),
            'deposit' => $this->amount,
            'use' => 0,
            'created_at' => $this->created_at,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'description':
                return $query->where('method', 'like', "%{$value}%")->orWhere('reff_no', 'like', "%{$value}%");

            case 'type':
                return $query->where(function ($q) use ($value) {
                    if ($value === 'deposit') {
                        $q->where('amount', '>', 0);
                    } elseif ($value === 'use') {
                        $q;
                    }
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
