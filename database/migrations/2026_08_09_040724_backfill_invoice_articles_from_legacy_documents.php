<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('invoices')
            || !Schema::hasTable('invoice_articles')
            || !Schema::hasTable('orders')
            || !Schema::hasTable('order_articles')
            || !Schema::hasTable('shipments')
            || !Schema::hasTable('shipment_articles')
        ) {
            return;
        }

        DB::transaction(function () {
            $this->backfillOrderInvoices();
            $this->backfillShipmentInvoices();
            $this->recalculateOrderDispatch();
        });
    }

    public function down(): void
    {
        //
    }

    private function invoicesMissingLines(): Collection
    {
        return DB::table('invoices')
            ->leftJoin('invoice_articles', 'invoice_articles.invoice_id', '=', 'invoices.id')
            ->whereNull('invoice_articles.id')
            ->select('invoices.*')
            ->get();
    }

    private function backfillOrderInvoices(): void
    {
        $invoices = $this->invoicesMissingLines()
            ->filter(fn ($invoice) => !empty($invoice->order_no))
            ->groupBy('order_no');

        foreach ($invoices as $orderNo => $orderInvoices) {
            $order = DB::table('orders')->where('order_no', $orderNo)->first();
            if (!$order) {
                continue;
            }

            $orderArticles = DB::table('order_articles')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get();

            if ($orderArticles->isEmpty()) {
                continue;
            }

            if ($orderInvoices->count() === 1) {
                $invoice = $orderInvoices->first();
                foreach ($orderArticles as $orderArticle) {
                    $this->insertInvoiceArticle(
                        (int) $invoice->id,
                        (int) $orderArticle->article_id,
                        $orderArticle->description,
                        (int) $orderArticle->ordered_pcs,
                        $invoice->created_at,
                        $invoice->updated_at,
                    );
                }
                continue;
            }

            $this->backfillSplitOrderInvoices($orderInvoices->values(), $orderArticles);
        }
    }

    private function backfillSplitOrderInvoices(Collection $invoices, Collection $orderArticles): void
    {
        $remainingByArticle = $orderArticles->mapWithKeys(fn ($line) => [
            (int) $line->article_id => (int) $line->ordered_pcs,
        ]);

        $totalAmount = max(1, (int) $invoices->sum(fn ($invoice) => (int) ($invoice->netAmount ?? 0)));
        $lastInvoiceId = (int) $invoices->last()->id;

        foreach ($invoices as $invoice) {
            $invoiceId = (int) $invoice->id;
            $ratio = ((int) ($invoice->netAmount ?? 0)) / $totalAmount;

            foreach ($orderArticles as $orderArticle) {
                $articleId = (int) $orderArticle->article_id;
                $remaining = (int) ($remainingByArticle->get($articleId) ?? 0);
                if ($remaining <= 0) {
                    continue;
                }

                $pcs = $invoiceId === $lastInvoiceId
                    ? $remaining
                    : min($remaining, max(0, (int) round(((int) $orderArticle->ordered_pcs) * $ratio)));

                if ($pcs <= 0) {
                    continue;
                }

                $this->insertInvoiceArticle(
                    $invoiceId,
                    $articleId,
                    $orderArticle->description,
                    $pcs,
                    $invoice->created_at,
                    $invoice->updated_at,
                );

                $remainingByArticle[$articleId] = $remaining - $pcs;
            }
        }
    }

    private function backfillShipmentInvoices(): void
    {
        $invoices = $this->invoicesMissingLines()
            ->filter(fn ($invoice) => !empty($invoice->shipment_no));

        foreach ($invoices as $invoice) {
            $shipment = DB::table('shipments')->where('shipment_no', $invoice->shipment_no)->first();
            if (!$shipment) {
                continue;
            }

            $cartonCount = max(1, (int) ($invoice->carton_count ?? 1));
            $shipmentArticles = DB::table('shipment_articles')
                ->where('shipment_id', $shipment->id)
                ->orderBy('id')
                ->get();

            foreach ($shipmentArticles as $shipmentArticle) {
                $this->insertInvoiceArticle(
                    (int) $invoice->id,
                    (int) $shipmentArticle->article_id,
                    $shipmentArticle->description,
                    (int) $shipmentArticle->shipment_pcs * $cartonCount,
                    $invoice->created_at,
                    $invoice->updated_at,
                );
            }
        }
    }

    private function insertInvoiceArticle(
        int $invoiceId,
        int $articleId,
        ?string $description,
        int $invoicePcs,
        ?string $createdAt,
        ?string $updatedAt,
    ): void {
        if ($invoicePcs <= 0) {
            return;
        }

        DB::table('invoice_articles')->insert([
            'invoice_id' => $invoiceId,
            'article_id' => $articleId,
            'description' => $description,
            'invoice_pcs' => $invoicePcs,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $updatedAt ?? now(),
        ]);
    }

    private function recalculateOrderDispatch(): void
    {
        DB::table('order_articles')->update(['dispatched_pcs' => 0]);

        $rows = DB::table('invoices')
            ->join('orders', 'orders.order_no', '=', 'invoices.order_no')
            ->join('invoice_articles', 'invoice_articles.invoice_id', '=', 'invoices.id')
            ->selectRaw('orders.id as order_id, invoice_articles.article_id, SUM(invoice_articles.invoice_pcs) as pcs')
            ->whereNotNull('invoices.order_no')
            ->groupBy('orders.id', 'invoice_articles.article_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('order_articles')
                ->where('order_id', $row->order_id)
                ->where('article_id', $row->article_id)
                ->update(['dispatched_pcs' => (int) $row->pcs]);
        }

        $orders = DB::table('order_articles')
            ->selectRaw('order_id, SUM(ordered_pcs) as ordered_pcs, SUM(dispatched_pcs) as dispatched_pcs')
            ->groupBy('order_id')
            ->get();

        foreach ($orders as $order) {
            $orderedPcs = (int) $order->ordered_pcs;
            $dispatchedPcs = (int) $order->dispatched_pcs;
            $status = $dispatchedPcs <= 0
                ? 'pending'
                : ($dispatchedPcs < $orderedPcs ? 'partially_invoiced' : 'invoiced');

            DB::table('orders')
                ->where('id', $order->order_id)
                ->update(['status' => $status]);
        }
    }
};
