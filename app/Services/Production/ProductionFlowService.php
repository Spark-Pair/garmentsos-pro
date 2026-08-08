<?php

namespace App\Services\Production;

use App\Models\Article;
use App\Models\Production;
use App\Models\ProductionFlow;
use App\Models\Setup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductionFlowService
{
    public function ready(): bool
    {
        return Schema::hasTable('production_flows');
    }

    public function normalizePartQuantities(mixed $value): Collection
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($value) ? $value : [])
            ->map(function ($row) {
                $row = (array) $row;

                return [
                    'part' => trim((string) ($row['part'] ?? '')),
                    'quantity' => (float) ($row['quantity'] ?? 0),
                ];
            })
            ->filter(fn ($row) => $row['part'] !== '' && $row['quantity'] > 0)
            ->groupBy(fn ($row) => strtolower($row['part']))
            ->map(fn ($rows) => [
                'part' => (string) $rows->first()['part'],
                'quantity' => (float) $rows->sum('quantity'),
            ])
            ->values();
    }

    public function articleLimit(Article $article): float
    {
        return (float) ($article->quantity ?? 0) + (float) ($article->extra_pcs ?? 0);
    }

    public function isCutting(?Setup $work): bool
    {
        return strcasecmp(trim((string) $work?->title), 'Cutting') === 0;
    }

    public function isCmt(?Setup $work): bool
    {
        return strcasecmp(trim((string) $work?->title), 'CMT | E') === 0
            || strcasecmp(trim((string) $work?->title), 'CMT') === 0;
    }

    public function isStitching(?Setup $work): bool
    {
        return strcasecmp(trim((string) $work?->title), 'Stitching | E') === 0
            || strcasecmp(trim((string) $work?->title), 'Stitching') === 0;
    }

    public function isCutToPack(?Setup $work): bool
    {
        return strcasecmp(trim((string) $work?->title), 'Cut To Pack') === 0
            || strcasecmp(trim((string) $work?->title), 'Cut to Pack') === 0;
    }

    public function cuttingReceivedByPart(int $articleId): Collection
    {
        return ProductionFlow::query()
            ->where('article_id', $articleId)
            ->where('movement_type', 'receive')
            ->whereHas('work', fn ($query) => $query->where('title', 'Cutting'))
            ->selectRaw('part, SUM(quantity) as total_quantity')
            ->groupBy('part')
            ->pluck('total_quantity', 'part')
            ->map(fn ($value) => (float) $value);
    }

    public function downstreamIssuedByPart(int $articleId, ?int $workId = null): Collection
    {
        $query = ProductionFlow::query()
            ->where('article_id', $articleId)
            ->where('movement_type', 'issue')
            ->whereHas('work', fn ($query) => $query->where('title', '!=', 'Cutting'));

        if ($workId) {
            $query->where('work_id', $workId);
        }

        return $query
            ->selectRaw('part, SUM(quantity) as total_quantity')
            ->groupBy('part')
            ->pluck('total_quantity', 'part')
            ->map(fn ($value) => (float) $value);
    }

    public function issueableByPart(int $articleId, ?int $workId = null): Collection
    {
        $cutting = $this->cuttingReceivedByPart($articleId);
        $issued = $this->downstreamIssuedByPart($articleId, $workId);

        return $cutting
            ->map(fn ($quantity, $part) => max(0, (float) $quantity - (float) ($issued[$part] ?? 0)))
            ->filter(fn ($quantity) => $quantity > 0);
    }

    public function issueableWorkIds(int $articleId): Collection
    {
        $cutting = $this->cuttingReceivedByPart($articleId);
        if ($cutting->isEmpty()) {
            return collect();
        }

        $issuedByWorkPart = ProductionFlow::query()
            ->where('article_id', $articleId)
            ->where('movement_type', 'issue')
            ->whereHas('work', fn ($query) => $query->where('title', '!=', 'Cutting'))
            ->selectRaw('work_id, part, SUM(quantity) as total_quantity')
            ->groupBy('work_id', 'part')
            ->get()
            ->groupBy('work_id')
            ->map(fn ($rows) => $rows->pluck('total_quantity', 'part')->map(fn ($value) => (float) $value));

        return Setup::where('type', 'worker_type')
            ->where('title', '!=', 'Cutting')
            ->pluck('id')
            ->filter(function ($workId) use ($cutting, $issuedByWorkPart) {
                $issued = $issuedByWorkPart->get($workId, collect());

                return $cutting->contains(function ($quantity, $part) use ($issued) {
                    return (float) $quantity - (float) ($issued[$part] ?? 0) > 0;
                });
            })
            ->map(fn ($workId) => (int) $workId)
            ->values();
    }

    public function receiveableByPart(string $ticket): Collection
    {
        $issued = ProductionFlow::query()
            ->where('ticket', $ticket)
            ->where('movement_type', 'issue')
            ->selectRaw('part, SUM(quantity) as total_quantity')
            ->groupBy('part')
            ->pluck('total_quantity', 'part')
            ->map(fn ($value) => (float) $value);

        $received = ProductionFlow::query()
            ->where('parent_ticket', $ticket)
            ->where('movement_type', 'receive')
            ->selectRaw('part, SUM(quantity) as total_quantity')
            ->groupBy('part')
            ->pluck('total_quantity', 'part')
            ->map(fn ($value) => (float) $value);

        return $issued
            ->map(fn ($quantity, $part) => max(0, (float) $quantity - (float) ($received[$part] ?? 0)))
            ->filter(fn ($quantity) => $quantity > 0);
    }

    public function validateCuttingReceive(Article $article, Collection $parts): void
    {
        $limit = $this->articleLimit($article);
        $received = $this->cuttingReceivedByPart((int) $article->id);

        foreach ($parts as $row) {
            $part = $row['part'];
            $total = (float) ($received[$part] ?? 0) + (float) $row['quantity'];
            if ($total > $limit) {
                throw ValidationException::withMessages([
                    'production_flows' => "{$part} cutting receive quantity cannot exceed {$limit}.",
                ]);
            }
        }
    }

    public function validateIssue(Article $article, Collection $parts, ?Setup $work = null): void
    {
        $available = $this->issueableByPart((int) $article->id, $work?->id ? (int) $work->id : null);

        foreach ($parts as $row) {
            $part = $row['part'];
            if ((float) $row['quantity'] > (float) ($available[$part] ?? 0)) {
                throw ValidationException::withMessages([
                    'production_flows' => "{$part} issue quantity cannot exceed " . ((float) ($available[$part] ?? 0)) . '.',
                ]);
            }
        }
    }

    public function validateReceive(string $ticket, Collection $parts): void
    {
        $available = $this->receiveableByPart($ticket);

        foreach ($parts as $row) {
            $part = $row['part'];
            if ((float) $row['quantity'] > (float) ($available[$part] ?? 0)) {
                throw ValidationException::withMessages([
                    'production_flows' => "{$part} receive quantity cannot exceed " . ((float) ($available[$part] ?? 0)) . '.',
                ]);
            }
        }
    }

    public function sync(Production $production, string $movementType, Collection $parts, ?string $parentTicket = null): void
    {
        if (!$this->ready()) {
            return;
        }

        if (!in_array($movementType, ['issue', 'receive'], true)) {
            throw ValidationException::withMessages([
                'production_flows' => 'Invalid production movement type.',
            ]);
        }

        $date = $movementType === 'issue'
            ? $production->issue_date
            : $production->receive_date;

        if (!$date) {
            throw ValidationException::withMessages([
                $movementType === 'issue' ? 'issue_date' : 'receive_date' => ucfirst($movementType) . ' date is required.',
            ]);
        }

        ProductionFlow::where('production_id', $production->id)->delete();

        foreach ($parts as $row) {
            ProductionFlow::create([
                'production_id' => $production->id,
                'article_id' => $production->article_id,
                'work_id' => $production->work_id,
                'worker_id' => $production->worker_id,
                'branch_id' => $production->branch_id,
                'movement_type' => $movementType,
                'part' => $row['part'],
                'quantity' => $row['quantity'],
                'ticket' => $production->ticket,
                'parent_ticket' => $parentTicket,
                'date' => $date,
            ]);
        }
    }
}
