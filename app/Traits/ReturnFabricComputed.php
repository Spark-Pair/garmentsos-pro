<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ReturnFabricComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'type' => 'Issued',
            'tag' => $this->tag,
            'quantity' => $this->quantity,
            'date' => $this->date->format('d-M-Y, D'),
            'employee_name' => $this->worker->employee_name,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'employee_name':
                return $query->where(function ($query) use ($value) {
                    $query->whereHas('worker', function ($q) use ($value) {
                        $q->where('employee_name', 'like', "%{$value}%");
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
