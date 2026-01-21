<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait EmployeeComputed
{
    public function toFormattedArray()
    {
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
                'Balance'=> number_format($this->balance),
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

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'type':
                return $query->where('type_id', $value);

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
