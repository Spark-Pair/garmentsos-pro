<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Services\Production\ProductionItemSyncService;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ProductionComputed
{
    public function toFormattedArray()
    {
        $items = app(ProductionItemSyncService::class);
        $tags = $items->tagsForPayload($this);
        $materials = $items->materialsForPayload($this);

        return [
            'id' => $this->id,
            'article_no' => $this->article->article_no,
            'worker_name' => $this->worker->employee_name . ' | ' . $this->work->title,
            'ticket' => $this->ticket,
            'issue_date' => $this->issue_date?->format('d-M-Y, D') ?? '-',
            'receive_date' => $this->receive_date?->format('d-M-Y, D') ?? '-',
            'quantity' => $this->article?->quantity,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'title' => $this->title,
            'parts' => $this->parts,
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
                'receive_date' => $this->receive_date?->format('Y-m-d'),
                'article_no' => $this->article?->article_no,
                'article' => $this->article,
                'work' => $this->work,
                'worker' => $this->worker,
                'worker_name' => $this->worker?->employee_name,
                'quantity' => $this->quantity,
                'rate' => $this->rate,
                'amount' => $this->amount,
                'title' => $this->title,
                'parts' => $this->parts,
                'materials' => $materials,
                'tags' => $tags,
                'creator' => $this->creator?->name,
            ],
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'article_no':
                return $query->whereHas('article', function ($query) use ($value) {
                    $query->where('article_no', 'like', "%{$value}%");
                });

            case 'worker_name':
                return $query->whereHas('worker', function ($query) use ($value) {
                    $query->where('employee_name', 'like', "%{$value}%");
                });

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
