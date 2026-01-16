<?php

namespace App\Traits;

use App\Models\Invoice;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ShipmentComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->shipment_no,
            'details' => [
                'Amount' => $this->netAmount,
                'Date' => $this->date->format('d-M-Y, D'),
            ],
            'isInvoiceHas' => $this->invoices()->exists(),
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
