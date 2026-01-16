<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait InvoiceComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->invoice_no,
            'details' => [
                'Customer' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount' => number_format($this->netAmount),
                'Reff. No.' => $this->order_no ?? $this->shipment_no,
            ],
            'data' => $this,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%$value%")
                    ->orWhereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });

            case 'reff_no':
                return $query->where('order_no', 'like', "%$value%")->orWhere('shipment_no', 'like', "%$value%");

            case 'city':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->whereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
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
