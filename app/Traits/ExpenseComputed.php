<?php

namespace App\Traits;

trait ExpenseComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'supplier_name' => $this->supplier->supplier_name,
            'reff_no' => $this->reff_no,
            'expense' => $this->expenseSetups->title,
            'lot_no' => $this->lot_no ?? '-',
            'amount' => number_format($this->amount),
            'remarks' => $this->remarks ?? 'No Remarks',
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->whereHas('supplier', function ($q) use ($value) {
                    $q->where('supplier_name', 'like', "%$value%");
                });

            case 'expense':
                return $query->where('expense', $value);

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
