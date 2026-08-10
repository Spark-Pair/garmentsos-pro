<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Models\ProductionFlow;
use App\Services\Production\ProductionItemSyncService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Schema;

trait ProductionComputed
{
    use SearchFilterHelpers;

    public function toFormattedArray()
    {
        $items = app(ProductionItemSyncService::class);
        $tags = $items->tagsForPayload($this);
        $materials = $items->materialsForPayload($this);
        $partQuantities = $this->partQuantitiesForPayload();
        $flowQuantity = collect($partQuantities)->max('quantity') ?? $this->quantity;
        $flowType = $this->issue_date ? 'Issue' : 'Receive';
        $receiveDate = $this->effectiveReceiveDate();
        $rate = $this->effectiveRate();
        $amount = $this->effectiveAmount();

        return [
            'id' => $this->id,
            'article_no' => $this->article->article_no,
            'worker_name' => $this->worker->employee_name . ' | ' . $this->work->title,
            'ticket' => $this->ticket,
            'issue_date' => $this->issue_date?->format('d-M-Y, D') ?? '-',
            'receive_date' => $receiveDate?->format('d-M-Y, D') ?? '-',
            'movement_type' => $flowType,
            'quantity' => $flowQuantity,
            'rate' => $rate,
            'amount' => $amount,
            'title' => $this->title,
            'parts' => $this->parts,
            'part_quantities' => $partQuantities,
            'materials' => $materials,
            'tags' => $tags,
            'oncontextmenu' => 'generateContextMenu(event)',
            'onclick' => 'generateModal(this)',
            'data' => [
                'id' => $this->id,
                'ticket' => $this->ticket,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('productions', $this),
                'issue_date' => $this->issue_date?->format('Y-m-d'),
                'receive_date' => $receiveDate?->format('Y-m-d'),
                'article_no' => $this->article?->article_no,
                'article' => $this->article,
                'work' => $this->work,
                'worker' => $this->worker,
                'worker_name' => $this->worker?->employee_name,
                'movement_type' => $flowType,
                'parent_ticket' => $this->partParentTicket(),
                'quantity' => $flowQuantity,
                'rate' => $rate,
                'amount' => $amount,
                'title' => $this->title,
                'parts' => $this->parts,
                'part_quantities' => $partQuantities,
                'materials' => $materials,
                'tags' => $tags,
                'creator' => $this->creator?->name,
                'issued_by_name' => $this->issued_by_name,
                'received_by_name' => $this->received_by_name,
            ],
        ];
    }

    private function partQuantitiesForPayload(): array
    {
        if (Schema::hasTable('production_flows')) {
            $this->loadMissing('productionFlows');
            if ($this->productionFlows->isNotEmpty()) {
                $movementType = $this->issue_date ? 'issue' : 'receive';
                $flows = $this->productionFlows->where('movement_type', $movementType);

                if ($flows->isEmpty()) {
                    $flows = $this->productionFlows;
                }

                return $flows
                    ->groupBy('part')
                    ->map(fn ($flows, $part) => [
                        'part' => (string) $part,
                        'quantity' => (float) $flows->sum('quantity'),
                        'movement_type' => ucfirst((string) $flows->first()->movement_type),
                    ])
                    ->values()
                    ->all();
            }
        }

        $quantity = (float) ($this->quantity ?? $this->article?->quantity ?? 0);

        return collect($this->parts ?? [])
            ->map(fn ($part) => [
                'part' => (string) $part,
                'quantity' => $quantity,
                'movement_type' => $this->issue_date ? 'Issue' : 'Receive',
            ])
            ->values()
            ->all();
    }

    private function partParentTicket(): ?string
    {
        if (!Schema::hasTable('production_flows')) {
            return null;
        }

        $this->loadMissing('productionFlows');

        if ($this->issue_date) {
            return null;
        }

        return $this->productionFlows
            ->pluck('parent_ticket')
            ->filter()
            ->first();
    }

    private function effectiveReceiveDate()
    {
        if ($this->receive_date) {
            return $this->receive_date;
        }

        if (!Schema::hasTable('production_flows') || !$this->ticket) {
            return null;
        }

        $date = ProductionFlow::query()
            ->where('parent_ticket', $this->ticket)
            ->where('movement_type', 'receive')
            ->max('date');

        return $date ? Carbon::parse($date) : null;
    }

    private function effectiveRate(): ?float
    {
        if ((float) ($this->rate ?? 0) > 0) {
            return (float) $this->rate;
        }

        $receive = $this->latestReceiveChild();

        return $receive && (float) ($receive->rate ?? 0) > 0 ? (float) $receive->rate : $this->rate;
    }

    private function effectiveAmount(): ?float
    {
        if ((float) ($this->amount ?? 0) > 0) {
            return (float) $this->amount;
        }

        $receive = $this->latestReceiveChild();

        return $receive && (float) ($receive->amount ?? 0) > 0 ? (float) $receive->amount : $this->amount;
    }

    private function latestReceiveChild(): ?self
    {
        if (!Schema::hasTable('production_flows') || !$this->ticket) {
            return null;
        }

        $childProductionId = ProductionFlow::query()
            ->where('parent_ticket', $this->ticket)
            ->where('movement_type', 'receive')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('production_id');

        return $childProductionId ? self::query()->find($childProductionId) : null;
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'article_no':
                $articleIds = $this->searchFilterMatchingIds(\App\Models\Article::class, 'article_no', $value);

                return $articleIds->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('article_id', $articleIds->all());

            case 'worker_name':
                $workerIds = $this->searchFilterMatchingIds(\App\Models\Employee::class, 'employee_name', $value);
                $workIds = $this->searchFilterMatchingIds(\App\Models\Setup::class, 'title', $value);

                if ($workerIds->isEmpty() && $workIds->isEmpty()) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where(function ($q) use ($workerIds, $workIds) {
                    if ($workerIds->isNotEmpty()) {
                        $q->whereIn('worker_id', $workerIds->all());
                    }

                    if ($workIds->isNotEmpty()) {
                        $method = $workerIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $q->{$method}('work_id', $workIds->all());
                    }
                });

            case 'part':
            case 'parts':
                $tokens = $this->searchFilterTokens($value);
                if ($tokens->isEmpty()) {
                    return $query;
                }

                return $query->where(function ($partQuery) use ($tokens) {
                    $partQuery->whereHas('productionFlows', function ($flowQuery) use ($tokens) {
                        foreach ($tokens as $token) {
                            $flowQuery->orWhere('part', 'like', "%{$token}%");
                        }
                    });

                    foreach ($tokens as $token) {
                        $partQuery->orWhere('parts', 'like', "%{$token}%");
                    }
                });

            case 'ticket':
                return $this->searchFilterWhereLikeAny($query, 'ticket', $value);

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
