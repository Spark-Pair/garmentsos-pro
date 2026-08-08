<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ReturnFabricComputed
{
    use SearchFilterHelpers;

    public function toFormattedArray()
    {
        $source = $this->sourceFabricLot();

        return [
            'id' => $this->id,
            'type' => 'Returned',
            'tag' => $this->tag,
            'quantity' => $this->quantity,
            'date' => $this->date->format('d-M-Y, D'),
            'supplier_name' => $source?->supplier?->supplier_name,
            'employee_name' => $this->worker->employee_name,
            'fabric' => $source?->fabric?->title,
            'color' => $source?->color,
            'unit' => $source?->unit,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }

    private function sourceFabricLot()
    {
        static $lotsByTag = null;

        if ($lotsByTag === null) {
            $lotsByTag = app(\App\Services\Branches\ModuleBranchService::class)
                ->applyScope(\App\Models\Fabric::with(['supplier', 'fabric'])->orderByDesc('id'), 'fabrics')
                ->get()
                ->unique(fn ($fabric) => (string) $fabric->tag)
                ->keyBy(fn ($fabric) => (string) $fabric->tag);
        }

        if ($lotsByTag->has((string) $this->tag)) {
            return $lotsByTag->get((string) $this->tag);
        }

        return app(\App\Services\Branches\ModuleBranchService::class)
            ->applyScope(\App\Models\Fabric::with(['supplier', 'fabric'])->where('tag', $this->tag), 'fabrics')
            ->latest('id')
            ->first();
    }

    private function sourceFabricTagsForFilter(string $key, $value)
    {
        $query = app(\App\Services\Branches\ModuleBranchService::class)
            ->applyScope(\App\Models\Fabric::query(), 'fabrics');

        if ($key === 'supplier_name') {
            $supplierIds = $this->searchFilterMatchingIds(\App\Models\Supplier::class, 'supplier_name', $value);

            if ($supplierIds->isEmpty()) {
                return collect();
            }

            $query->whereIn('supplier_id', $supplierIds->all());
        } elseif ($key === 'fabric') {
            $query->where('fabric_id', $value);
        } elseif (in_array($key, ['color', 'unit'], true)) {
            $this->searchFilterWhereLikeAny($query, $key, $value);
        }

        return $query->pluck('tag')->filter()->unique()->values();
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'employee_name':
                $workerIds = $this->searchFilterMatchingIds(\App\Models\Employee::class, 'employee_name', $value);

                return $workerIds->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('worker_id', $workerIds->all());

            case 'fabric':
            case 'supplier_name':
            case 'color':
            case 'unit':
                $tags = $this->sourceFabricTagsForFilter($key, $value);

                return $tags->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('tag', $tags->all());

            case 'type':
                if ($value === 'Issued') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'Received') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'Returned') {
                    return $query->where('quantity', '>', 0);
                }
                return $query;

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;

                \App\Support\DateRange::apply($query, 'date', $start, $end);
                return $query;

            case 'tag':
            case 'remarks':
                return $this->searchFilterWhereLikeAny($query, $key, $value);

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
