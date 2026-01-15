<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait VoucherComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->voucher_no,
            'details' => [
                'Supplier' => $this->supplier ? $this->supplier->supplier_name : app('client_company')->name,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount' => $this->payments->sum('amount'),
            ],
            'total_payment' => $this->payments->sum('amount'),
            'data' => $this,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
            return $query->where(function ($query) use ($value) {

                // Case 1: supplier exists → supplier_name
                $query->whereHas('supplier', function ($q) use ($value) {
                    $q->where('supplier_name', 'like', "%{$value}%");
                })

                // Case 2: supplier does NOT exist → fallback to client_company name
                ->orWhere(function ($q) use ($value) {
                    $q->whereDoesntHave('supplier')
                    ->where(app('client_company')->name, 'like', "%{$value}%");
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
