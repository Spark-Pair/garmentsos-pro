<?php

namespace App\Traits;

trait PhysicalQuantityComputed
{
    use SearchFilterHelpers;

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'article_no':
                $articleIds = $this->searchFilterMatchingIds(\App\Models\Article::class, 'article_no', $value);

                return $articleIds->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('article_id', $articleIds->all());

            case 'processed_by':
                return $query->whereHas('article', fn ($q) => $this->searchFilterWhereLikeAny($q, 'processed_by', $value));

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
