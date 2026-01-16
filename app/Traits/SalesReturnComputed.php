<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SalesReturnComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'customer' => $this->invoice->customer->customer_name . ' | ' . $this->invoice->customer->city->title,
            'article_no' => $this->article->article_no,
            'invoice_no' => $this->invoice->invoice_no,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer':
                return $query->whereHas('invoice', function ($q) use ($value) {
                    $q->whereHas('customer', function ($q2) use ($value) {
                        $q2->where('customer_name', 'like', "%{$value}%")
                        ->orWhereHas('city', function ($q3) use ($value) {
                            $q3->where('title', 'like', "%{$value}%");
                        });
                    });
                });

            case 'article_no':
                return $query->whereHas('article', function ($query) use ($value) {
                    $query->where('article_no', 'like', "%{$value}%");
                });

            case 'invoice_no':
                return $query->whereHas('invoice', function ($query) use ($value) {
                    $query->where('invoice_no', 'like', "%{$value}%");
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
