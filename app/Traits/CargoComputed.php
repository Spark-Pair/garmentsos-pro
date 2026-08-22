<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CargoComputed
{
    public function toFormattedArray()
    {
        $invoices = collect($this->invoices)->map(fn($invoice) => [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'shipment_no' => $invoice->shipment_no,
            'date' => $invoice->date->format('Y-m-d'),
            'carton_count' => (int) ($invoice->carton_count ?? 0),
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'customer_name' => $invoice->customer->customer_name,
                'city' => $invoice->customer->city ? [
                    'id' => $invoice->customer->city->id,
                    'title' => $invoice->customer->city->title,
                    'short_title' => $invoice->customer->city->short_title,
                ] : null,
            ] : null,
        ])->sortBy(
            fn($invoice) => $invoice['invoice_no'] ?? '',
            SORT_NATURAL | SORT_FLAG_CASE
        )->values();

        return [
            'id' => $this->id,
            'name' => $this->cargo_no,
            'details' => [
                'Cargo Name' => $this->cargo_name,
                'Date' => $this->date->format('d-M-Y, D'),
            ],
            'data' => [
                'id' => $this->id,
                'cargo_no' => $this->cargo_no,
                'cargo_name' => $this->cargo_name,
                'date' => $this->date?->format('Y-m-d'),
                'invoices' => $invoices,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('cargo', $this),
                'created_at' => $this->created_at?->format('Y-m-d, h:i A'),
                'updated_at' => $this->updated_at?->format('Y-m-d, h:i A'),
                'creator' => $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;

                \App\Support\DateRange::apply($query, 'date', $start, $end);
                return $query;

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
