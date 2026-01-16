<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CRComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'amount' => collect($this->new_payments)->sum('amount'),
            'c_r_no' => $this->c_r_no,
            'voucher_no' => $this->voucher->voucher_no,
            'supplier_name' => $this->voucher->supplier->supplier_name ?? app('client_company')->name,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->where(function ($query) use ($value) {

                    // Case 1: supplier exists → supplier_name
                    $query->whereHas('voucher.supplier', function ($q) use ($value) {
                        $q->where('supplier_name', 'like', "%{$value}%");
                    })

                    // Case 2: supplier does NOT exist → fallback to client_company name
                    ->orWhere(function ($q) use ($value) {
                        $q->whereDoesntHave('voucher.supplier')
                        ->where(app('client_company')->name, 'like', "%{$value}%");
                    });

                });

            case 'voucher_no':
                return $query->whereHas('voucher', function ($q) use ($value) {
                    $q->where('voucher_no', 'like', "%{$value}%");
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
