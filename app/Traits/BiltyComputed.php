<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait BiltyComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'customer_name' => $this->invoice->customer->customer_name . ' | ' . $this->invoice->customer->city->title,
            'invoice_no' => $this->invoice->invoice_no,
            'cargo_name' => $this->invoice->cargo_name,
            'bilty_no' => $this->bilty_no . ' | ' . $this->invoice->cotton_count,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->whereHas('invoice.customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%{$value}%")
                    ->orWhereHas('city', function ($sq) use ($value) {
                        $sq->where('title', 'like', "%{$value}%");
                    });
                });

            case 'invoice_no':
                return $query->whereHas('invoice', function ($q) use ($value) {
                    $q->where('invoice_no', 'like', "%{$value}%");
                });

            case 'cargo_name':
                return $query->whereHas('invoice', function ($q) use ($value) {
                    $q->where('cargo_name', 'like', "%{$value}%");
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
