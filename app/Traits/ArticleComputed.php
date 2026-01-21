<?php

namespace App\Traits;

use App\Models\OrderArticles;
use App\Models\Setup;
use App\Models\ShipmentArticles;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ArticleComputed
{
    public function orderedQuantity(): Attribute
    {
        return Attribute::get(function () {
            return $this->orderArticles()->sum('ordered_pcs');
        });
    }

    public function soldQuantity(): Attribute
    {
        return Attribute::get(function () {

            // 1️⃣ dispatched pcs from orders
            $dispatchedFromOrders = $this->orderArticles()
                ->sum('dispatched_pcs');

            // 2️⃣ shipment pcs × coton_count
            $shipmentSold = $this->shipmentArticles()
                ->with('shipment.invoices')
                ->get()
                ->sum(function ($shipmentArticle) {

                    if (!$shipmentArticle->shipment) {
                        return 0;
                    }

                    return $shipmentArticle->shipment->invoices->sum(function ($invoice) use ($shipmentArticle) {
                        return $shipmentArticle->shipment_pcs * ($invoice->coton_count ?? 1);
                    });
                });

            return $dispatchedFromOrders + $shipmentSold;
        });
    }

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'image' => $this->image == 'no_image_icon.png' ? '/images/no_image_icon.png' : '/storage/uploads/images/' . $this->image,
            'name' => $this->article_no,
            'status' => $this->sales_rate == 0.00 ? 'no_rate' : 'transparent',
            'category' => $this->category,
            'season' => $this->season,
            'size' => $this->size,
            'details' => [
                'Category' => str_replace('_', ' ', $this->category),
                'Season' => ucFirst($this->season),
                'Size' => strtoupper(str_replace('_', ' ', $this->size)),
            ],
            'sales_rate'=> number_format($this->sales_rate),
            'processed_by'=> ucwords($this->processed_by) ?? '-',
            'fabric_type'=> $this->fabric_type,
            'quantity'=> $this->quantity,
            'current_stock'=> $this->quantity - $this->ordered_quantity,
            'ordered_quantity'=> $this->ordered_quantity,
            'ready_date'=> $this->date,
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
