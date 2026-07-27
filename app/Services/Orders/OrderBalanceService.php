<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Support\Facades\Schema;

class OrderBalanceService
{
    private array $customerBalanceCache = [];

    public function branchScopeForModule(string $moduleKey): array
    {
        try {
            $branches = app(ModuleBranchService::class);

            $branchIds = collect($branches->selectedBranchIdsForModule($moduleKey) ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($branchIds === [] && $branches->shouldFilterRecords($moduleKey)) {
                $selectedBranchId = $branches->selectedBranchIdForModule($moduleKey);
                if ($selectedBranchId) {
                    $branchIds = [(int) $selectedBranchId];
                }
            }

            $mainBranchId = $branches->mainBranch()?->id;
            $includeNullBranchRecords = $mainBranchId
                && in_array((int) $mainBranchId, $branchIds, true);

            return [
                'branch_ids' => $branchIds,
                'include_null_branch_records' => (bool) $includeNullBranchRecords,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'branch_ids' => [],
                'include_null_branch_records' => false,
            ];
        }
    }

    public function pendingForOrder(Order $order): float
    {
        $order->loadMissing('invoices');

        return max(0, $this->orderAmount($order) - $this->invoicedAmount($order));
    }

    public function pendingForCustomer(Customer|int|null $customer, ?array $branchIds = null, bool $includeNullBranchRecords = false): float
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;
        if (!$customerId) {
            return 0;
        }

        $branchIds = collect($branchIds ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $cacheKey = implode(':', [
            (int) $customerId,
            implode(',', $branchIds),
            $includeNullBranchRecords ? 'null' : 'strict',
        ]);

        if (array_key_exists($cacheKey, $this->customerBalanceCache)) {
            return $this->customerBalanceCache[$cacheKey];
        }

        $orders = Order::with('invoices')
            ->where('customer_id', $customerId)
            ->when($branchIds !== [] && Schema::hasColumn('orders', 'branch_id'), function ($query) use ($branchIds, $includeNullBranchRecords) {
                $query->where(function ($nested) use ($branchIds, $includeNullBranchRecords) {
                    $nested->whereIn('branch_id', $branchIds);
                    if ($includeNullBranchRecords) {
                        $nested->orWhereNull('branch_id');
                    }
                });
            })
            ->get();

        return $this->customerBalanceCache[$cacheKey] = (float) $orders->sum(
            fn (Order $order) => $this->pendingForOrder($order)
        );
    }

    private function orderAmount(Order $order): float
    {
        return (float) (
            $order->netAmount
            ?? $order->net_amount
            ?? $order->total_amount
            ?? $order->amount
            ?? 0
        );
    }

    private function invoicedAmount(Order $order): float
    {
        return (float) $order->invoices->sum(
            fn ($invoice) => (float) (
                $invoice->netAmount
                ?? $invoice->net_amount
                ?? $invoice->total_amount
                ?? $invoice->amount
                ?? 0
            )
        );
    }
}
