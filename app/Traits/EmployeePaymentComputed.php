<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait EmployeePaymentComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => ucwords($this->employee->employee_name) . ' | ' . explode('|', $this->employee->type->title)[0],
            'details' => [
                'Category'=> ucwords($this->employee->category),
                'Method'=> $this->method,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount'=> number_format($this->amount),
            ],
            'date' => $this->date->format('d-M-Y, D'),
            'type' => $this->employee->type->title,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'employee_name':
                return $query->whereHas('employee', function ($q) use ($value) {
                    $q->where('employee_name', 'like', "%$value%");
                });

            case 'category':
                return $query->whereHas('employee', function ($q) use ($value) {
                    $q->where('category', $value);
                });

            case 'type':
                return $query->whereHas('employee', function ($q) use ($value) {
                    $q->where('type_id', $value);
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
