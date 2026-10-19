<?php

namespace App\Traits;

use App\Services\ArticleStockService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ArticleComputed
{
    public function orderedQuantity(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('ordered_pcs', $this->attributes)) {
                return (float) $this->attributes['ordered_pcs'];
            }

            return (float) (app(ArticleStockService::class)
                ->summaries([$this->id])
                ->get($this->id, [])['ordered_quantity_pcs'] ?? 0);
        });
    }

    public function soldQuantity(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('sold_pcs', $this->attributes)) {
                return (float) $this->attributes['sold_pcs'];
            }

            return (float) (app(ArticleStockService::class)
                ->summaries([$this->id])
                ->get($this->id, [])['invoiced_quantity_pcs'] ?? 0);
        });
    }

    public function toFormattedArray()
    {
        $stock = app(ArticleStockService::class)->summaries([$this->id])->get($this->id, []);

        return [
            'id' => $this->id,
            'image' => $this->image == 'no_image_icon.png' ? '/images/no_image_icon.png' : '/storage/uploads/images/' . $this->image,
            'name' => $this->article_no,
            'creator' => $this->creator?->name,
            'status' => $this->sales_rate == 0.00 ? 'no_rate' : 'transparent',
            'category' => $this->category,
            'season' => $this->season,
            'size' => $this->size,
            'details' => [
                'Category' => str_replace('_', ' ', $this->category),
                'Season' => ucFirst($this->season),
                'Size' => strtoupper(str_replace('_', ' ', $this->size)),
            ],
            'sales_rate'=> \App\Support\Money::format($this->sales_rate),
            'processed_by'=> $this->processed_by ? ucwords($this->processed_by) : '-',
            'fabric_type'=> $this->fabric_type,
            'quantity'=> $this->quantity,
            'current_stock'=> (int) ($stock['current_stock_pcs'] ?? 0),
            'ordered_quantity'=> (int) ($stock['ordered_quantity_pcs'] ?? 0),
            'ready_date'=> $this->date?->format('Y-m-d'),
            'rates_array'=> $this->rates_array,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'category':
                return $query->where('category', $value);

            case 'season':
                return $query->where('season', $value);

            case 'size':
                return $query->where('size', $value);

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
