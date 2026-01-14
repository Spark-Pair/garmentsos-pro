<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait Filterable
{
    public function scopeApplyFilters($query, Request $request)
    {
        $filters = $request->except(['_token', 'limit', 'page']);

        // 1. Get limit from request, default to null if not provided
        $limit = $request->get('limit');

        foreach ($filters as $key => $value) {
            if (empty($value) && $value !== '0') continue;

            if (method_exists($this, 'scopeApplyModelFilters')) {
                $query->applyModelFilters($key, $value);
            } else {
                $query->where($key, 'like', "%{$value}%");
            }
        }

        // 2. APPLY LIMIT HERE (Ye missing tha)
        // Agar limit request mein hai, toh sirf utne records lo
        if ($limit) {
            $query->limit($limit);
        }

        // 3. Get and Format
        return $query->get()->map->toFormattedArray();
    }
}
