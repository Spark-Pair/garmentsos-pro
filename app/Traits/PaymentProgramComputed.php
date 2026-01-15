<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait PaymentProgramComputed
{
    public function beneficiary(): Attribute
    {
        return Attribute::get(function () {

            if ($this->category == 'supplier') return $this->subCategory->supplier_name;
            if ($this->category == 'self_account') return $this->subCategory->account_title;
            if ($this->category == 'waiting') return $this->remarks ?? '-';

            return '-';
        });
    }

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'customer_name' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
            'o_p_no' => $this->order_no ?? $this->program_no,
            'category' => $this->category,
            'beneficiary' => $this->beneficiary,
            'amount' => $this->amount,
            'payment' => $this->payment,
            'balance' => $this->balance,
            'status' => $this->status,
            'type' => $this->order_no ? 'order' : 'program',
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

            case 'type':
                return $query->where(function ($q) use ($value) {
                    if ($value == 'order') {
                        $q->whereNotNull('order_no');
                    } else {
                        $q->whereNull('order_no');
                    }
                });

            case 'beneficiary':
                return $query->where(function($q) use ($value) {
                    // Has subCategory: check supplier_name or account_title
                    $q->whereHas('subCategory', function($sq) use ($value) {
                        $sq->where('supplier_name', 'like', "%$value%")
                        ->orWhere('account_title', 'like', "%$value%");
                    })
                    // Does NOT have subCategory: check remarks
                    ->orWhere(function($q) use ($value) {
                        $q->whereDoesntHave('subCategory')
                        ->where('remarks', 'like', "%$value%");
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
