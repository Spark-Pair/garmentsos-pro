<?php

namespace App\Services\Production;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\IssuedFabric;
use App\Models\Production;
use App\Models\ProductionMaterial;
use App\Models\ProductionTag;
use App\Models\ReturnFabric;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductionItemSyncService
{
    public function sync(Production $production, array $tags, array $materials): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        DB::transaction(function () use ($production, $tags, $materials) {
            ProductionTag::where('production_id', $production->id)->delete();
            ProductionMaterial::where('production_id', $production->id)->delete();
            InventoryTransaction::where('source_type', Production::class)
                ->where('source_id', $production->id)
                ->delete();

            foreach ($this->normalizeTags($tags) as $tag) {
                ProductionTag::create([
                    'production_id' => $production->id,
                    'branch_id' => $production->branch_id,
                    'tag' => $tag['tag'],
                    'quantity' => $tag['quantity'],
                    'unit' => $tag['unit'] ?? null,
                    'worker_id' => $production->worker_id,
                ]);
            }

            foreach ($this->normalizeMaterials($materials) as $material) {
                $row = ProductionMaterial::create([
                    'production_id' => $production->id,
                    'branch_id' => $production->branch_id,
                    'inventory_item_id' => $material['inventory_item_id'] ?? null,
                    'title' => $material['title'],
                    'unit' => $material['unit'] ?? null,
                    'quantity' => $material['quantity'],
                    'unit_price' => $material['unit_price'] ?? null,
                    'amount' => $material['amount'] ?? null,
                ]);

                if (!empty($material['inventory_item_id'])) {
                    $item = InventoryItem::whereKey($material['inventory_item_id'])->lockForUpdate()->first();
                    if (!$item) {
                        throw ValidationException::withMessages([
                            'materials' => "Inventory item for {$row->title} is no longer available.",
                        ]);
                    }

                    $transactions = InventoryTransaction::where('inventory_item_id', $item->id)
                        ->lockForUpdate()
                        ->get();
                    $available = (float) $transactions->where('direction', 'in')->sum('quantity')
                        - (float) $transactions->where('direction', 'out')->sum('quantity');
                    if ((float) $material['quantity'] > $available) {
                        throw ValidationException::withMessages([
                            'materials' => "{$row->title} quantity cannot exceed available stock ({$available} {$item->unit}).",
                        ]);
                    }

                    InventoryTransaction::create([
                        'branch_id' => $production->branch_id,
                        'inventory_item_id' => $material['inventory_item_id'],
                        'direction' => 'out',
                        'date' => $production->issue_date ?? $production->receive_date ?? now()->toDateString(),
                        'quantity' => $material['quantity'],
                        'unit' => $material['unit'] ?? $item?->unit,
                        'unit_price' => $material['unit_price'] ?? null,
                        'amount' => $material['amount'] ?? null,
                        'source_type' => Production::class,
                        'source_id' => $production->id,
                        'reference_no' => $production->ticket,
                        'remarks' => 'Used in production material: ' . $row->title,
                    ]);
                }
            }
        });
    }

    public function tagsForPayload(Production $production): array
    {
        if (!$this->tablesReady()) {
            return $this->normalizeTags($production->tags ?? [])->values()->all();
        }

        $production->loadMissing('productionTags');
        if ($production->productionTags->isNotEmpty()) {
            return $production->productionTags
                ->map(fn ($row) => [
                    'tag' => $row->tag,
                    'quantity' => $row->quantity,
                    'unit' => $row->unit,
                ])
                ->values()
                ->all();
        }

        return $this->normalizeTags($production->tags ?? [])->values()->all();
    }

    public function materialsForPayload(Production $production): array
    {
        if (!$this->tablesReady()) {
            return $this->normalizeMaterials($production->materials ?? [])->values()->all();
        }

        $production->loadMissing('productionMaterials.inventoryItem');
        if ($production->productionMaterials->isNotEmpty()) {
            return $production->productionMaterials
                ->map(fn ($row) => [
                    'inventory_item_id' => $row->inventory_item_id,
                    'title' => $row->title,
                    'unit' => $row->unit,
                    'quantity' => $row->quantity,
                    'unit_price' => $row->unit_price,
                    'amount' => $row->amount,
                    'source' => $row->inventory_item_id ? 'inventory' : 'manual',
                    'stock_quantity' => $row->inventoryItem?->stock_quantity,
                ])
                ->values()
                ->all();
        }

        return $this->normalizeMaterials($production->materials ?? [])->values()->all();
    }

    public function normalizeTags(array|Collection $tags): Collection
    {
        return collect($tags)
            ->map(function ($item) {
                $item = (array) $item;
                return [
                    'tag' => trim((string) ($item['tag'] ?? '')),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit' => $item['unit'] ?? null,
                ];
            })
            ->filter(fn ($item) => $item['tag'] !== '' && $item['quantity'] > 0)
            ->values();
    }

    public function normalizeMaterials(array|Collection $materials): Collection
    {
        return collect($materials)
            ->map(function ($item) {
                $item = (array) $item;
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== ''
                    ? (float) $item['unit_price']
                    : null;

                return [
                    'inventory_item_id' => !empty($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null,
                    'title' => trim((string) ($item['title'] ?? $item['name'] ?? '')),
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => isset($item['amount']) && $item['amount'] !== ''
                        ? (float) $item['amount']
                        : ($unitPrice !== null ? $unitPrice * $quantity : null),
                ];
            })
            ->filter(fn ($item) => $item['title'] !== '' && $item['quantity'] > 0)
            ->values();
    }

    public function workerTagBalances(array|Collection $workerIds, string $module = 'productions'): Collection
    {
        $workerIds = collect($workerIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($workerIds->isEmpty()) {
            return collect();
        }

        $balances = collect();
        $branches = app(ModuleBranchService::class);

        $addBalance = function (int $workerId, string $tag, float $quantity) use (&$balances) {
            $tag = trim($tag);
            if ($tag === '' || $quantity == 0.0) {
                return;
            }

            $key = $workerId . '|' . $tag;
            $current = $balances->get($key, [
                'worker_id' => $workerId,
                'tag' => $tag,
                'issued_quantity' => 0.0,
                'returned_quantity' => 0.0,
                'production_quantity' => 0.0,
                'available_quantity' => 0.0,
            ]);

            if ($quantity > 0) {
                $current['issued_quantity'] += $quantity;
            } elseif ($quantity < 0) {
                $current['available_quantity'] += $quantity;
            }

            $balances->put($key, $current);
        };

        $issuedRows = $branches->applyRelatedScope(
                IssuedFabric::query()->whereIn('worker_id', $workerIds),
                'fabrics',
                $module,
            )
            ->selectRaw('worker_id, tag, SUM(quantity) as total_quantity')
            ->groupBy('worker_id', 'tag')
            ->get();

        foreach ($issuedRows as $row) {
            $addBalance((int) $row->worker_id, (string) $row->tag, (float) $row->total_quantity);
        }

        $returnRows = $branches->applyRelatedScope(
                ReturnFabric::query()->whereIn('worker_id', $workerIds),
                'fabrics',
                $module,
            )
            ->selectRaw('worker_id, tag, SUM(quantity) as total_quantity')
            ->groupBy('worker_id', 'tag')
            ->get();

        foreach ($returnRows as $row) {
            $key = (int) $row->worker_id . '|' . trim((string) $row->tag);
            $current = $balances->get($key, [
                'worker_id' => (int) $row->worker_id,
                'tag' => trim((string) $row->tag),
                'issued_quantity' => 0.0,
                'returned_quantity' => 0.0,
                'production_quantity' => 0.0,
                'available_quantity' => 0.0,
            ]);
            $current['returned_quantity'] += (float) $row->total_quantity;
            $current['available_quantity'] -= (float) $row->total_quantity;
            $balances->put($key, $current);
        }

        if (Schema::hasTable('production_tags')) {
            $productionRows = $branches->applyRelatedScope(
                    ProductionTag::query()->whereIn('worker_id', $workerIds),
                    'productions',
                    $module,
                )
                ->selectRaw('worker_id, tag, SUM(quantity) as total_quantity, MAX(unit) as unit')
                ->groupBy('worker_id', 'tag')
                ->get();

            foreach ($productionRows as $row) {
                $key = (int) $row->worker_id . '|' . trim((string) $row->tag);
                $current = $balances->get($key, [
                    'worker_id' => (int) $row->worker_id,
                    'tag' => trim((string) $row->tag),
                    'issued_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'production_quantity' => 0.0,
                    'available_quantity' => 0.0,
                ]);
                $current['production_quantity'] += (float) $row->total_quantity;
                $current['available_quantity'] -= (float) $row->total_quantity;
                $current['unit'] = $row->unit ?: ($current['unit'] ?? null);
                $balances->put($key, $current);
            }
        }

        return $balances
            ->map(function (array $row) {
                $row['available_quantity'] = $row['issued_quantity'] - $row['returned_quantity'] - $row['production_quantity'];
                return $row;
            })
            ->filter(fn (array $row) => $row['available_quantity'] > 0)
            ->values()
            ->groupBy('worker_id');
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('production_tags')
            && Schema::hasTable('production_materials')
            && Schema::hasTable('inventory_transactions');
    }
}
