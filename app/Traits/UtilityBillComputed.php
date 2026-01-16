<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait UtilityBillComputed
{
    public function status(): Attribute
    {
        return Attribute::get(function () {

            if ($this->is_paid) {
                return 'Paid';
            }

            if (!$this->due_date) {
                return '-';
            }

            $dueDate = $this->due_date->startOfDay();
            $today   = now()->startOfDay();

            return match (true) {
                $dueDate->lt($today) => 'Overdue',
                $dueDate->eq($today) => 'Due Today',
                $dueDate->gt($today) => 'Upcoming',
                default              => '-',
            };
        });
    }

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'bill_type' => $this->account->billType->title,
            'location' => $this->account->location->title,
            'account_title' => $this->account->account_title,
            'account_no' => $this->account->account_no,
            'month' => $this->month->format('F Y'),
            'units' => $this->units ?? '-',
            'amount' => $this->amount,
            'due_date' => $this->due_date->format('d-M-Y, D'),
            'is_paid' => $this->is_paid,
            'status' => $this->status,
            'oncontextmenu' => 'generateContextMenu(event)',
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'bill_type':
                return $query->whereHas('account.billType', function ($q) use ($value) {
                    $q->where('title', 'like', "%{$value}%");
                });

            case 'location':
                return $query->whereHas('account.location', function ($q) use ($value) {
                    $q->where('title', 'like', "%{$value}%");
                });

            case 'account_title':
                return $query->whereHas('account', function ($q) use ($value) {
                    $q->where('account_title', 'like', "%{$value}%");
                });

            case 'account_no':
                return $query->whereHas('account', function ($q) use ($value) {
                    $q->where('account_no', 'like', "%{$value}%");
                });

            case 'status':
                return $query->when($value === 'paid', fn ($q) =>
                        $q->where('is_paid', true)
                    )
                    ->when($value === 'overdue', fn ($q) =>
                        $q->where('is_paid', false)->whereDate('due_date', '<', today())
                    )
                    ->when($value === 'due-today', fn ($q) =>
                        $q->where('is_paid', false)->whereDate('due_date', today())
                    )
                    ->when($value === 'upcoming', fn ($q) =>
                        $q->where('is_paid', false)->whereDate('due_date', '>', today())
                    );

            // case 'date':
            //     $start = $value['start'] ?? null;
            //     $end   = $value['end'] ?? null;

            //     if (!$start || !$end) return $query->where('method', 'cash');


            //     return $query->where(function ($q) use ($start, $end) {
            //         // 1️⃣ slip_date exists
            //         $q->Where(function ($q) use ($start, $end) {
            //             $q->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
            //         });
            //     });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
