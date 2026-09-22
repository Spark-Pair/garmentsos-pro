<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PhysicalQuantity;
use App\Models\ShipmentArticles;
use App\Services\Branches\ModuleBranchService;
use App\Traits\SearchFilterHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class PhysicalQuantityReportService
{
    use SearchFilterHelpers;

    public function __construct(private readonly ArticleStockService $stockService)
    {
    }

    public function getIndexRows(Request|array $filters = [], ?int $limit = null, ?array $branchIds = null, bool $includeNullBranchRecords = false): Collection
    {
        $branches = app(ModuleBranchService::class);
        $articleQuery = Article::query()->orderByDesc('id');

        if ($branchIds !== null && !empty($branchIds) && Schema::hasColumn('articles', 'branch_id')) {
            $articleQuery->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('articles.branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('articles.branch_id');
                }
            });
        } else {
            $articleQuery = $branches->applyRelatedScope($articleQuery, 'articles', 'physical_quantities');
        }

        $this->applyArticleFilters($articleQuery, $filters);
        $articles = $articleQuery->get();

        if ($articles->isEmpty()) {
            return collect();
        }

        $physicalQuery = PhysicalQuantity::query()
            ->whereIn('article_id', $articles->pluck('id'))
            ->orderByDesc('id');

        if ($branchIds !== null && !empty($branchIds) && Schema::hasColumn('physical_quantities', 'branch_id')) {
            $physicalQuery->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            });
        } else {
            $physicalQuery = $branches->applyScope($physicalQuery, 'physical_quantities');
        }

        $rows = $physicalQuery->get();
        $groupedRows = $this->mapArticleRows($articles, $rows, $branchIds, $includeNullBranchRecords);

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

    protected function applyArticleFilters(Builder $query, Request|array $filters): void
    {
        if ($filters instanceof Request) {
            $filters = $filters->except(['_token', 'limit', 'page']);
        }

        foreach ($filters as $key => $value) {
            if (empty($value) && $value !== '0') {
                continue;
            }

            if ($key === 'article_no') {
                $this->applyArticleNoFilter($query, (string) $value);
                continue;
            }

            if ($key === 'article_id') {
                $query->whereKey((int) $value);
                continue;
            }

            if ($key === 'processed_by') {
                $processedBy = mb_strtolower(trim((string) $value));
                $query->whereRaw('LOWER(processed_by) LIKE ?', ["%{$processedBy}%"]);
                continue;
            }

            if ($key === 'shipment' && in_array($value, ['karachi', 'other', 'all'], true)) {
                $this->applyShipmentFilter($query, (string) $value);
            }
        }
    }

    protected function applyArticleNoFilter(Builder $query, string $value): void
    {
        $tokens = $this->searchFilterTokens($value);
        if ($tokens->isEmpty()) {
            return;
        }

        $query->where(function (Builder $articleQuery) use ($tokens) {
            foreach ($tokens as $token) {
                $bounds = $this->searchFilterRangeBounds($token);
                if (!$bounds) {
                    $articleQuery->orWhere('article_no', 'like', "%{$token}%");
                    continue;
                }

                [$startNumber, $endNumber, $width] = $bounds;
                $start = str_pad((string) $startNumber, $width, '0', STR_PAD_LEFT);
                $end = str_pad((string) $endNumber, $width, '0', STR_PAD_LEFT);
                $articleQuery->orWhere(function (Builder $rangeQuery) use ($start, $end, $startNumber, $endNumber) {
                    $rangeQuery->whereBetween('article_no', [$start, $end]);

                    $driver = $rangeQuery->getConnection()->getDriverName();
                    if (in_array($driver, ['mysql', 'mariadb'], true)) {
                        $rangeQuery->orWhereRaw(
                            "CAST(SUBSTRING_INDEX(article_no, '|', -1) AS UNSIGNED) BETWEEN ? AND ?",
                            [$startNumber, $endNumber]
                        );
                    } elseif ($driver === 'sqlite') {
                        $rangeQuery->orWhereRaw(
                            "CAST(CASE WHEN instr(article_no, '|') > 0 THEN substr(article_no, instr(article_no, '|') + 1) ELSE article_no END AS INTEGER) BETWEEN ? AND ?",
                            [$startNumber, $endNumber]
                        );
                    }
                });
            }
        });
    }

    protected function applyShipmentFilter(Builder $query, string $shipment): void
    {
        $query->whereHas('shipmentArticles.shipment', function (Builder $shipmentQuery) use ($shipment) {
            if ($shipment === 'karachi') {
                $shipmentQuery->where('city', 'karachi');
            } elseif ($shipment === 'other') {
                $shipmentQuery->where('city', '!=', 'karachi');
            }
        });
    }

    protected function mapArticleRows(Collection $articles, Collection $rows, ?array $branchIds = null, bool $includeNullBranchRecords = false): Collection
    {
        $articleIds = $articles->pluck('id')->unique()->values();

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
        $fallbackStockMap = $this->stockService->summaries($articleIds);

        $rowsByArticle = $rows->groupBy('article_id');

        return $articles
            ->map(function (Article $article) use ($rowsByArticle, $shipmentCitiesMap, $stockMap, $fallbackStockMap) {
                $items = $rowsByArticle->get($article->id, collect());
                /** @var \App\Models\PhysicalQuantity|null $model */
                $model = $items->first();
                $stock = $stockMap->get($article->id, []);
                $fallbackStock = $fallbackStockMap->get($article->id, []);
                $unit = (float) ($stock['unit'] ?? $fallbackStock['unit'] ?? $article->pcs_per_packet ?? 0);
                $scopedHasQuantity = collect([
                    $stock['total_quantity_pcs'] ?? 0,
                    $stock['orderable_quantity_pcs'] ?? 0,
                    $stock['ordered_quantity_pcs'] ?? 0,
                    $stock['current_stock_pcs'] ?? 0,
                    $stock['received_quantity_pcs'] ?? 0,
                ])->contains(fn ($value) => (float) $value > 0);

                if ($items->isEmpty() && !$scopedHasQuantity && !empty($fallbackStock)) {
                    $stock = $fallbackStock;
                }

                $totalPcs = (float) ($stock['total_quantity_pcs'] ?? 0);
                $totalPackets = (float) ($stock['total_quantity_packets'] ?? 0);
                $orderablePackets = (float) ($stock['orderable_quantity_packets'] ?? 0);
                $orderedPackets = (float) ($stock['ordered_quantity_packets'] ?? 0);
                $receivedPackets = (float) ($stock['received_quantity_packets'] ?? 0);
                $invoicedPackets = (float) ($stock['invoiced_quantity_packets'] ?? 0);
                $shipmentinvoicedPackets= (float) ($stock['shipment_invoiced_quantity_packets'] ?? 0);
                $returnPackets = (float) ($stock['return_quantity_packets'] ?? 0);
                $adjustmentPackets = (float) ($stock['adjustment_quantity_packets'] ?? 0);
                $currentStockPackets = (float) ($stock['current_stock_packets'] ?? 0);
                $remainingPackets = (float) ($stock['remaining_quantity_packets'] ?? 0);
                $orderablePcs = (float) ($stock['orderable_quantity_pcs'] ?? 0);
                $orderedPcs = (float) ($stock['ordered_quantity_pcs'] ?? 0);
                $receivedPcs = (float) ($stock['received_quantity_pcs'] ?? 0);
                $invoicedPcs = (float) ($stock['invoiced_quantity_pcs'] ?? 0);
                $shipmentInvoicedPcs = (float) ($stock['shipment_invoiced_quantity_pcs'] ?? 0);
                $returnPcs = (float) ($stock['return_quantity_pcs'] ?? 0);
                $adjustmentPcs = (float) ($stock['adjustment_quantity_pcs'] ?? 0);
                $currentStockPcs = (float) ($stock['current_stock_pcs'] ?? 0);
                $remainingPcs = (float) ($stock['remaining_quantity_pcs'] ?? 0);
                $shipment = $this->resolveShipment($shipmentCitiesMap->get($article->id, collect()));
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
                    'id' => $model?->id ?? 'article-' . $article->id,
                    'article_id' => $article->id,
                    'article_no' => $article->article_no,
                    'size' => $article->size,
                    'processed_by' => filled($article->processed_by) ? $article->processed_by : '-',
                    'unit' => $article->pcs_per_packet,
                    'total_quantity' => $unit > 0
                        ? floor($totalPcs / 12) . ' Dz. | ' . $this->formatPacketQuantity($totalPackets)
                        : $this->formatPcsQuantity($totalPcs),
                    'orderable_quantity' => $this->formatStockQuantity($orderablePackets, $orderablePcs, $unit),
                    'ordered_quantity' => $this->formatStockQuantity($orderedPackets, $orderedPcs, $unit),
                    'received_quantity' => $this->formatStockQuantity($receivedPackets, $receivedPcs, $unit),
                    'invoiced_quantity' => $this->formatStockQuantity($invoicedPackets, $invoicedPcs, $unit),
                    'shipment_invoiced_quantity' => $this->formatStockQuantity($shipmentinvoicedPackets, $shipmentInvoicedPcs, $unit),
                    'return_quantity' => $this->formatStockQuantity($returnPackets, $returnPcs, $unit),
                    'adjustment_quantity' => $this->formatStockQuantity($adjustmentPackets, $adjustmentPcs, $unit),
                    'current_stock' => $this->formatStockQuantity($currentStockPackets, $currentStockPcs, $unit),
                    'a_category' => $this->formatStockQuantity((float) ($stock['a_category_packets'] ?? 0), (float) ($stock['a_category_pcs'] ?? 0), $unit),
                    'b_category' => $this->formatStockQuantity((float) ($stock['b_category_packets'] ?? 0), (float) ($stock['b_category_pcs'] ?? 0), $unit),
                    'c_category' => $this->formatStockQuantity((float) ($stock['c_category_packets'] ?? 0), (float) ($stock['c_category_pcs'] ?? 0), $unit),
                    'remaining_quantity' => $this->formatStockQuantity($remainingPackets, $remainingPcs, $unit),
                    'shipment' => $shipment,
                    'total_pcs_numeric' => $totalPcs,
                    'orderable_pcs_numeric' => $orderablePcs,
                    'ordered_pcs_numeric' => $orderedPcs,
                    'received_pcs_numeric' => $receivedPcs,
                    'current_stock_pcs_numeric' => $currentStockPcs,
                    'total_packets_numeric' => $totalPackets,
                    'orderable_packets_numeric' => $orderablePackets,
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
            ->filter(fn (array $item) => !empty($item['partial_records'])
                || (float) ($item['total_packets_numeric'] ?? 0) > 0
                || (float) ($item['orderable_packets_numeric'] ?? 0) > 0
                || (float) ($item['ordered_packets_numeric'] ?? 0) > 0
                || (float) ($item['current_stock_packets_numeric'] ?? 0) > 0
                || (float) ($item['total_pcs_numeric'] ?? 0) > 0
                || (float) ($item['orderable_pcs_numeric'] ?? 0) > 0
                || (float) ($item['ordered_pcs_numeric'] ?? 0) > 0
                || (float) ($item['current_stock_pcs_numeric'] ?? 0) > 0)
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

    protected function formatStockQuantity(float|int $packets, float|int $pcs, float|int $unit): string
    {
        if ((float) $unit > 0) {
            return $this->formatPacketQuantity($packets);
        }

        return $this->formatPcsQuantity($pcs);
    }

    protected function formatPcsQuantity(float|int $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        if ((float) $formatted === 0.0) {
            return '0';
        }

        return $formatted . ' - PCs';
    }
}
