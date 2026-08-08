<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('orders')
            || !Schema::hasTable('order_articles')
            || !Schema::hasTable('invoices')
            || !Schema::hasTable('invoice_articles')
        ) {
            return;
        }

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

    public function down(): void
    {
        //
    }
};
