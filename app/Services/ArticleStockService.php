<?php

namespace App\Services;

use App\Models\Article;
use App\Models\InvoiceArticles;
use App\Models\OrderArticles;
use App\Models\PhysicalQuantity;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class ArticleStockService
{
    private array $summaryCache = [];

    public function summaries($articleIds, ?int $excludeOrderId = null, int|array|null $branchId = null, bool $includeNullBranchRecords = false): Collection
    {
        $branchIds = collect(is_array($branchId) ? $branchId : (filled($branchId) ? [$branchId] : []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $articleIds = collect($articleIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($articleIds->isEmpty()) {
            return collect();
        }

        $cachePrefix = implode('|', [
            (string) ($excludeOrderId ?? ''),
            implode(',', $branchIds),
            $includeNullBranchRecords ? '1' : '0',
        ]);
        $cached = collect();
        $missingArticleIds = $articleIds
            ->reject(function (int $articleId) use ($cachePrefix, $cached) {
                $cacheKey = $cachePrefix . '|' . $articleId;
                if (!array_key_exists($cacheKey, $this->summaryCache)) {
                    return false;
                }

                $cached->put($articleId, $this->summaryCache[$cacheKey]);

                return true;
            })
            ->values();

        if ($missingArticleIds->isEmpty()) {
            return $articleIds->mapWithKeys(fn (int $articleId) => [$articleId => $cached->get($articleId)]);
        }

        $articles = Article::query()
            ->whereIn('id', $missingArticleIds)
            ->get(['id', 'quantity', 'extra_pcs', 'pcs_per_packet'])
            ->keyBy('id');

        $physicalRows = PhysicalQuantity::query()
            ->whereIn('article_id', $missingArticleIds)
            ->when(!empty($branchIds) && Schema::hasColumn('physical_quantities', 'branch_id'), fn ($query) => $query->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            }))
            ->get(['article_id', 'packets', 'category', 'sales_return_id'])
            ->groupBy('article_id');

        $orderedPcs = OrderArticles::query()
            ->whereIn('article_id', $missingArticleIds)
            ->when($excludeOrderId, fn ($query) => $query->where('order_id', '!=', $excludeOrderId))
            ->when(!empty($branchIds) && Schema::hasColumn('orders', 'branch_id'), fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            })))
            ->selectRaw('article_id, SUM(ordered_pcs) as quantity')
            ->groupBy('article_id')
            ->pluck('quantity', 'article_id');

        $invoicedPcs = InvoiceArticles::query()
            ->whereIn('article_id', $missingArticleIds)
            ->when(!empty($branchIds) && Schema::hasColumn('invoices', 'branch_id'), fn ($query) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            })))
            ->selectRaw('article_id, SUM(invoice_pcs) as quantity')
            ->groupBy('article_id')
            ->pluck('quantity', 'article_id');

        $returnRows = SalesReturn::query()
            ->whereIn('article_id', $missingArticleIds)
            ->when(!empty($branchIds) && Schema::hasColumn('sales_returns', 'branch_id'), fn ($query) => $query->where(function ($scope) use ($branchIds, $includeNullBranchRecords) {
                $scope->whereIn('branch_id', $branchIds);
                if ($includeNullBranchRecords) {
                    $scope->orWhereNull('branch_id');
                }
            }))
            ->selectRaw("article_id, type, SUM(quantity) as quantity")
            ->groupBy('article_id', 'type')
            ->get()
            ->groupBy('article_id');

        $fresh = $missingArticleIds->mapWithKeys(function (int $articleId) use (
            $articles,
            $physicalRows,
            $orderedPcs,
            $invoicedPcs,
            $returnRows
        ) {
            $article = $articles->get($articleId);
            $unit = (float) ($article?->pcs_per_packet ?? 0);
            $rows = $physicalRows->get($articleId, collect());
            $returns = $returnRows->get($articleId, collect());

            $receivedPackets = (float) $rows
                ->reject(fn (PhysicalQuantity $row) => filled($row->sales_return_id)
                    || in_array((string) $row->category, ['sales_return', 'adjustment'], true))
                ->sum('packets');

            $categoryPackets = collect(['a', 'b', 'c'])->mapWithKeys(fn (string $category) => [
                $category => (float) $rows->where('category', $category)->sum('packets'),
            ]);

            $totalPcs = (int) ($article?->quantity ?? 0) + (int) ($article?->extra_pcs ?? 0);
            $receivedPcs = $unit > 0 ? (int) round($receivedPackets * $unit) : 0;
            $totalOrderedPcs = (int) ($orderedPcs->get($articleId) ?? 0);
            $totalInvoicedPcs = (int) ($invoicedPcs->get($articleId) ?? 0);
            $totalReturnPcs = (int) ($returns->firstWhere('type', 'return')?->quantity ?? 0);
            $totalAdjustmentPcs = (int) ($returns->firstWhere('type', 'adjustment')?->quantity ?? 0);
            $currentStockPcs = max(0, $receivedPcs - $totalInvoicedPcs + $totalReturnPcs + $totalAdjustmentPcs);
            $orderablePcs = max(0, $totalPcs - $totalOrderedPcs - $totalInvoicedPcs + $totalReturnPcs + $totalAdjustmentPcs);
            $remainingReceivePcs = max(0, $totalPcs - $receivedPcs);

            $summary = [
                'unit' => $unit,
                'total_quantity_pcs' => $totalPcs,
                'received_quantity_pcs' => $receivedPcs,
                'ordered_quantity_pcs' => $totalOrderedPcs,
                'invoiced_quantity_pcs' => $totalInvoicedPcs,
                'return_quantity_pcs' => $totalReturnPcs,
                'adjustment_quantity_pcs' => $totalAdjustmentPcs,
                'current_stock_pcs' => $currentStockPcs,
                'orderable_quantity_pcs' => $orderablePcs,
                'remaining_quantity_pcs' => $remainingReceivePcs,
                'a_category_pcs' => $unit > 0 ? (int) round($categoryPackets['a'] * $unit) : 0,
                'b_category_pcs' => $unit > 0 ? (int) round($categoryPackets['b'] * $unit) : 0,
                'c_category_pcs' => $unit > 0 ? (int) round($categoryPackets['c'] * $unit) : 0,
            ];

            foreach ($summary as $key => $value) {
                if (str_ends_with($key, '_pcs')) {
                    $summary[str_replace('_pcs', '_packets', $key)] = $unit > 0 ? $value / $unit : 0;
                }
            }

            return [$articleId => $summary];
        });

        foreach ($fresh as $articleId => $summary) {
            $this->summaryCache[$cachePrefix . '|' . $articleId] = $summary;
        }

        return $articleIds->mapWithKeys(fn (int $articleId) => [
            $articleId => $fresh->get($articleId, $cached->get($articleId, [])),
        ]);
    }
}
