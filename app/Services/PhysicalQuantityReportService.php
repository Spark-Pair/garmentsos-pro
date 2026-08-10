<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PhysicalQuantity;
use App\Models\ShipmentArticles;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class PhysicalQuantityReportService
{
    public function __construct(private readonly ArticleStockService $stockService)
    {
    }

    public function getIndexRows(Request|array $filters = [], ?int $limit = null, ?array $branchIds = null, bool $includeNullBranchRecords = false): Collection
    {
        $branches = app(ModuleBranchService::class);
        $query = PhysicalQuantity::query()->orderByDesc('id');
        if ($branchIds !== null && !empty($branchIds) && Schema::hasColumn('physical_quantities', 'branch_id')) {
            $query->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            });
        } else {
            $query = $branches->applyScope($query, 'physical_quantities');
        }

        $articleScope = $this->articleBranchScope($branchIds, $includeNullBranchRecords);
        $query->whereHas('article', $articleScope)
            ->with(['article' => $articleScope]);

        $this->applyFilters($query, $filters);
        $rows = $query->get()->filter(fn (PhysicalQuantity $row) => $row->article);
        $groupedRows = $this->mapArticleRows($rows, $branchIds, $includeNullBranchRecords);

        if ($limit) {
            return $groupedRows->take($limit)->values();
        }

        return $groupedRows;
    }

    public function getArticleReportRows(array $filters = [], string $reportType = 'altration', ?array $branchIds = null, bool $includeNullBranchRecords = false): Collection
    {
        return $this->getIndexRows($filters, null, $branchIds, $includeNullBranchRecords)
            ->map(function (array $row) use ($reportType) {
                $processedBy = trim((string) ($row['processed_by'] ?? ''));
                $orderedQty = $row['ordered_quantity'] ?? $this->formatPacketQuantity((float) ($row['ordered_packets_numeric'] ?? 0));
                $currentStockQty = $row['current_stock'] ?? $this->formatPacketQuantity((float) ($row['current_stock_packets_numeric'] ?? 0));
                $receivedQty = $row['received_quantity'] ?? $this->formatPacketQuantity((float) ($row['received_packets_numeric'] ?? 0));
                $remainingQty = $row['remaining_quantity'] ?? $this->formatPacketQuantity((float) ($row['remaining_packets_numeric'] ?? 0));

                return [
                    'article_no' => $row['article_no'] ?? '-',
                    'proceed_by' => $processedBy !== '' ? $processedBy : '-',
                    'primary_qty' => $reportType === 'stock' ? $orderedQty : $receivedQty,
                    'secondary_qty' => $reportType === 'stock' ? $currentStockQty : $remainingQty,
                    'primary_qty_numeric' => $reportType === 'stock'
                        ? (float) ($row['ordered_packets_numeric'] ?? 0)
                        : (float) ($row['received_packets_numeric'] ?? 0),
                    'secondary_qty_numeric' => $reportType === 'stock'
                        ? (float) ($row['current_stock_packets_numeric'] ?? 0)
                        : (float) ($row['remaining_packets_numeric'] ?? 0),
                ];
            })
            ->sortBy(function (array $row) {
                return mb_strtolower(($row['article_no'] ?? '') . ' ' . ($row['proceed_by'] ?? ''));
            })
            ->values();
    }

    public function getArticleOptions(?array $branchIds = null, bool $includeNullBranchRecords = false): array
    {
        $query = Article::query()->orderByDesc('id');
        if ($branchIds !== null && !empty($branchIds) && Schema::hasColumn('articles', 'branch_id')) {
            $query->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            });
        } else {
            $query = app(ModuleBranchService::class)->applyRelatedScope($query, 'articles', 'reports_physical_quantity');
        }

        return $query
            ->get(['id', 'article_no', 'processed_by'])
            ->mapWithKeys(function (Article $article) {
                $processedBy = trim((string) $article->processed_by);
                $suffix = $processedBy !== '' ? ' | ' . $processedBy : '';

                return [
                    $article->id => [
                        'text' => $article->article_no . $suffix,
                    ],
                ];
            })
            ->all();
    }

    protected function articleBranchScope(?array $branchIds = null, bool $includeNullBranchRecords = false): callable
    {
        return function ($articleQuery) use ($branchIds, $includeNullBranchRecords) {
            if (!Schema::hasColumn('articles', 'branch_id')) {
                return;
            }

            if ($branchIds !== null && !empty($branchIds)) {
                $articleQuery->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                    $scope->whereIn('articles.branch_id', $branchIds);
                    if ($includeNullBranchRecords) {
                        $scope->orWhereNull('articles.branch_id');
                    }
                });
                return;
            }

            $branches = app(ModuleBranchService::class);
            if (!$branches->shouldFilterRelatedRecords('physical_quantities', 'articles')) {
                return;
            }

            $branchIdsForPhysical = $branches->selectedBranchIdsForModule('physical_quantities');
            if (empty($branchIdsForPhysical)) {
                $articleQuery->whereRaw('1 = 0');
                return;
            }

            $includeNullForMain = \App\Models\Branch::query()
                ->whereIn('id', $branchIdsForPhysical)
                ->where('is_main', true)
                ->exists();

            $articleQuery->where(function ($scope) use ($branchIdsForPhysical, $includeNullForMain) {
                $scope->whereIn('articles.branch_id', $branchIdsForPhysical);
                if ($includeNullForMain) {
                    $scope->orWhereNull('articles.branch_id');
                }
            });
        };
    }

    protected function selectedBranchIdsForModule(string $moduleKey): array
    {
        $branches = app(ModuleBranchService::class);

        return $branches->shouldFilterRecords($moduleKey)
            ? array_values(array_filter($branches->selectedBranchIdsForModule($moduleKey), fn ($id) => is_numeric($id)))
            : [];
    }

    protected function applyFilters(Builder $query, Request|array $filters): void
    {
        if ($filters instanceof Request) {
            $query->applyFilters($filters, false, true);
            return;
        }

        if (!empty($filters['article_id'])) {
            $query->where('article_id', (int) $filters['article_id']);
        }

        if (!empty($filters['article_no'])) {
            $articleNo = $filters['article_no'];
            $query->whereHas('article', fn (Builder $articleQuery) => $articleQuery->where('article_no', 'like', "%{$articleNo}%"));
        }

        if (!empty($filters['processed_by'])) {
            $processedBy = mb_strtolower(trim((string) $filters['processed_by']));
            $query->whereHas('article', function (Builder $articleQuery) use ($processedBy) {
                $articleQuery->whereRaw('LOWER(processed_by) LIKE ?', ["%{$processedBy}%"]);
            });
        }

        if (!empty($filters['shipment']) && in_array($filters['shipment'], ['karachi', 'other', 'all'], true)) {
            $shipment = $filters['shipment'];

            $query->whereHas('article.shipmentArticles.shipment', function (Builder $shipmentQuery) use ($shipment) {
                if ($shipment === 'karachi') {
                    $shipmentQuery->where('city', 'karachi');
                } elseif ($shipment === 'other') {
                    $shipmentQuery->where('city', '!=', 'karachi');
                }
            });
        }
    }

    protected function mapArticleRows(Collection $rows, ?array $branchIds = null, bool $includeNullBranchRecords = false): Collection
    {
        $articleIds = $rows->pluck('article_id')->unique()->values();

        if ($articleIds->isEmpty()) {
            return collect();
        }

        $shipmentCitiesQuery = ShipmentArticles::query()
            ->whereIn('article_id', $articleIds)
            ->whereHas('shipment')
            ->with('shipment:id,city,branch_id');

        $shipmentBranchIds = $branchIds ?? $this->selectedBranchIdsForModule('physical_quantities');
        if (!empty($shipmentBranchIds) && Schema::hasColumn('shipments', 'branch_id')) {
            $shipmentCitiesQuery->whereHas('shipment', function (Builder $shipmentQuery) use ($shipmentBranchIds, $includeNullBranchRecords) {
                $shipmentQuery->where(function (Builder $scope) use ($shipmentBranchIds, $includeNullBranchRecords) {
                    $scope->whereIn('shipments.branch_id', $shipmentBranchIds);
                    if ($includeNullBranchRecords) {
                        $scope->orWhereNull('shipments.branch_id');
                    }
                });
            });
        }

        $shipmentCitiesMap = $shipmentCitiesQuery
            ->get()
            ->groupBy('article_id')
            ->map(fn (Collection $items) => $items->pluck('shipment.city')->filter()->unique()->values());

        $branches = app(ModuleBranchService::class);
        $stockMap = $this->stockService->summaries(
            $articleIds,
            null,
            $branchIds ?? ($branches->shouldFilterRecords('physical_quantities') ? $branches->selectedBranchIdForModule('physical_quantities') : null),
            $includeNullBranchRecords
        );

        return $rows
            ->groupBy('article_id')
            ->map(function (Collection $items) use ($shipmentCitiesMap, $stockMap) {
                /** @var \App\Models\PhysicalQuantity $model */
                $model = $items->first();
                $article = $model->article;
                $stock = $stockMap->get($model->article_id, []);
                $totalPcs = (float) ($stock['total_quantity_pcs'] ?? 0);
                $totalPackets = (float) ($stock['total_quantity_packets'] ?? 0);
                $orderablePackets = (float) ($stock['orderable_quantity_packets'] ?? 0);
                $orderedPackets = (float) ($stock['ordered_quantity_packets'] ?? 0);
                $receivedPackets = (float) ($stock['received_quantity_packets'] ?? 0);
                $invoicedPackets = (float) ($stock['invoiced_quantity_packets'] ?? 0);
                $returnPackets = (float) ($stock['return_quantity_packets'] ?? 0);
                $adjustmentPackets = (float) ($stock['adjustment_quantity_packets'] ?? 0);
                $currentStockPackets = (float) ($stock['current_stock_packets'] ?? 0);
                $remainingPackets = (float) ($stock['remaining_quantity_packets'] ?? 0);
                $shipment = $this->resolveShipment($shipmentCitiesMap->get($model->article_id, collect()));
                $partialRecords = $items
                    ->sortByDesc('id')
                    ->map(function (PhysicalQuantity $item) {
                        $category = str_replace('_', ' ', (string) $item->category);
                        $date = $item->date
                            ? date('d-M-Y', strtotime((string) $item->date))
                            : '-';

                        return [
                            'id' => $item->id,
                            'date' => $date,
                            'category' => ucwords($category),
                            'packets' => $this->formatPacketQuantity((float) $item->packets),
                            'source' => $this->isSalesReturnQuantity($item) ? 'Sales Return' : 'Physical Quantity',
                            'created_by' => $item->creator?->name ?? '-',
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $model->id,
                    'article_id' => $model->article_id,
                    'article_no' => $article->article_no,
                    'processed_by' => $article->processed_by,
                    'unit' => $article->pcs_per_packet,
                    'total_quantity' => floor($totalPcs / 12) . ' Dz. | ' . $totalPackets,
                    'orderable_quantity' => $this->formatPacketQuantity($orderablePackets),
                    'ordered_quantity' => $this->formatPacketQuantity($orderedPackets),
                    'received_quantity' => $this->formatPacketQuantity($receivedPackets),
                    'invoiced_quantity' => $this->formatPacketQuantity($invoicedPackets),
                    'return_quantity' => $this->formatPacketQuantity($returnPackets),
                    'adjustment_quantity' => $this->formatPacketQuantity($adjustmentPackets),
                    'current_stock' => $this->formatPacketQuantity($currentStockPackets),
                    'a_category' => $this->formatPacketQuantity((float) ($stock['a_category_packets'] ?? 0)),
                    'b_category' => $this->formatPacketQuantity((float) ($stock['b_category_packets'] ?? 0)),
                    'c_category' => $this->formatPacketQuantity((float) ($stock['c_category_packets'] ?? 0)),
                    'remaining_quantity' => $this->formatPacketQuantity($remainingPackets),
                    'shipment' => $shipment,
                    'total_packets_numeric' => $totalPackets,
                    'ordered_packets_numeric' => $orderedPackets,
                    'received_packets_numeric' => $receivedPackets,
                    'invoiced_packets_numeric' => $invoicedPackets,
                    'return_packets_numeric' => $returnPackets,
                    'adjustment_packets_numeric' => $adjustmentPackets,
                    'current_stock_packets_numeric' => $currentStockPackets,
                    'remaining_packets_numeric' => $remainingPackets,
                    'partial_records' => $partialRecords,
                    'onclick' => 'generateModal(this)',
                    'oncontextmenu' => 'generateContextMenu(event)',
                ];
            })
            ->values()
            ->sortBy(fn($item) => (float) $item['article_no'])
            ->values();
    }

    protected function resolveShipment(Collection $cities): string
    {
        if ($cities->isEmpty()) {
            return '-';
        }

        $normalizedCities = $cities
            ->map(fn ($city) => mb_strtolower((string) $city))
            ->filter()
            ->unique()
            ->values();

        $hasKarachi = $normalizedCities->contains('karachi');

        if ($hasKarachi && $normalizedCities->count() === 1) {
            return 'Karachi';
        }

        if ($hasKarachi && $normalizedCities->count() > 1) {
            return 'All';
        }

        return 'Other';
    }

    protected function isSalesReturnQuantity(PhysicalQuantity $item): bool
    {
        return (string) $item->category === 'sales_return' || filled($item->sales_return_id);
    }

    protected function formatPacketQuantity(float|int $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted;
    }
}
