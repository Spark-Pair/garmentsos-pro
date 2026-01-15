<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait Filterable
{
    public function scopeApplyFilters($query, Request $request, $returnCollection = true)
    {
        $filters = $request->except(['_token', 'limit', 'page']);

        // Transform date range inputs (dual input fields)
        if (isset($filters['date_range_start']) && isset($filters['date_range_end'])) {
            $filters['date'] = [
                'start' => $filters['date_range_start'],
                'end' => $filters['date_range_end'],
            ];
            unset($filters['date_range_start'], $filters['date_range_end']);
        }

        $limit = $request->get('limit');

        foreach ($filters as $key => $value) {
            if (empty($value) && $value !== '0') continue;

            if (method_exists($this, 'scopeApplyModelFilters')) {
                $query->applyModelFilters($key, $value);
            } else {
                $query->where($key, 'like', "%{$value}%");
            }
        }

        if ($limit) {
            $query->limit($limit);
        }

        if ($returnCollection) {
            return $query->get()->map->toFormattedArray();
        }

        return $query;
    }
}
