<?php

namespace App\Traits;

trait PhysicalQuantityComputed
{
    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'article_no':
                return $query->whereHas('article', fn ($q) =>
                    $q->where('article_no', 'like', "%{$value}%")
                );

            case 'processed_by':
                return $query->whereHas('article', fn ($q) =>
                    $q->where('processed_by', 'like', "%{$value}%")
                );

            case 'shipment':
                return $query->whereHas('article.shipmentArticles.shipment', function($q) use ($value) {
                    if ($value === 'karachi') {
                        $q->where('city', 'karachi');
                    } elseif ($value === 'other') {
                        $q->where('city', '!=', 'karachi');
                    } elseif ($value === 'all') {
                        // tricky, needs subquery to detect both karachi and other cities
                    }
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
