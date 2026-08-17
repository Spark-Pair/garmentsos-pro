<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait OrderComputed
{
    public function toFormattedArray()
    {
        $customerName = trim(($this->customer?->customer_name ?? '-') . ' | ' . ($this->customer?->city?->title ?? '-'), ' |');
        $articles = $this->articles
            ? $this->articles->map(fn($orderArticle) => [
                'id' => $orderArticle->id,
                'description' => $orderArticle->description,
                'ordered_pcs' => (int) ($orderArticle->ordered_pcs ?? 0),
                'invoice_pcs' => (int) ($orderArticle->invoice_pcs ?? 0),
                'shipment_pcs' => (int) ($orderArticle->shipment_pcs ?? 0),
                'dispatched_pcs' => (int) ($orderArticle->dispatched_pcs ?? 0),
                'article' => $orderArticle->article ? [
                    'id' => $orderArticle->article->id,
                    'article_no' => $orderArticle->article->article_no,
                    'description' => $orderArticle->article->description,
                    'fabric_type' => $orderArticle->article->fabric_type,
                    'pcs_per_packet' => $orderArticle->article->pcs_per_packet,
                    'sales_rate' => $orderArticle->article->sales_rate,
                ] : null,
            ])->values()
            : collect();
        $remainingOrder = $this->articles
            ? $this->articles->sum(function ($article) {
                $ordered = (int) ($article->ordered_pcs ?? 0);
                $dispatched = max(0, (int) ($article->dispatched_pcs ?? 0));

                return max(0, $ordered - $dispatched);
            })
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->order_no,
            'order_no' => $this->order_no,
            'date' => $this->date?->format('d-M-Y, D'),
            'customer_name' => $customerName,
            'discount' => (float) ($this->discount ?? 0),
            'net_amount' => (float) ($this->netAmount ?? 0),
            'balance_order' => $remainingOrder,
            'details' => [
                'Date' => $this->date?->format('d-M-Y, D'),
                'Order No' => $this->order_no,
                'Customer' => $customerName,
                'Discount' => number_format((float) ($this->discount ?? 0), 1) . '%',
                'Net Amount' => \App\Support\Money::format((float) ($this->netAmount ?? 0)),
                'Balance Order' => number_format($remainingOrder) . ' Pcs',
            ],
            'status' => $this->status,
            'data' => [
                'id' => $this->id,
                'order_no' => $this->order_no,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('orders', $this),
                'date' => $this->date->format('Y-m-d'),
                'discount' => (float) ($this->discount ?? 0),
                'netAmount' => (float) ($this->netAmount ?? 0),
                'deliver_to' => $this->deliver_to,
                'customer' => $this->customer ? [
                    'id' => $this->customer->id,
                    'customer_name' => $this->customer->customer_name,
                    'urdu_title' => $this->customer->urdu_title,
                    'address' => $this->customer->address,
                    'phone_number' => $this->customer->phone_number,
                    'city' => $this->customer->city ? [
                        'id' => $this->customer->city->id,
                        'title' => $this->customer->city->title,
                        'short_title' => $this->customer->city->short_title,
                    ] : null,
                ] : null,
                'articles' => $articles,
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
            case 'customer_name':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%$value%")
                    ->orWhereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });
                
            case 'city':
                return $query->whereHas('customer.city', function ($q) use ($value) {
                    $q->where('title', 'like', "%$value%")
                    ->orWhere('short_title', 'like', "%$value%");
                });
                
            case 'category':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('category', 'like', "%$value%");
                });

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
