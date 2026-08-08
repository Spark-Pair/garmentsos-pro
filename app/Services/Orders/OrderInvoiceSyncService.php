<?php

namespace App\Services\Orders;

use App\Models\Article;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderArticles;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderInvoiceSyncService
{
    public function normalizeInvoiceLines(array $lines): Collection
    {
        return collect($lines)
            ->filter(fn ($line) => is_array($line))
            ->map(function ($line) {
                return [
                    'article_id' => (int) ($line['article_id'] ?? $line['id'] ?? 0),
                    'order_article_id' => (int) ($line['order_article_id'] ?? 0),
                    'description' => $line['description'] ?? null,
                    'invoice_pcs' => (int) ($line['invoice_pcs'] ?? $line['invoice_quantity'] ?? 0),
                ];
            })
            ->filter(fn ($line) => $line['article_id'] > 0 && $line['invoice_pcs'] > 0)
            ->values();
    }

    public function validateInvoiceAgainstOrder(Order $order, Collection $lines, ?Invoice $editingInvoice = null): void
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'articles_in_invoice' => 'Please select at least one invoice article.',
            ]);
        }

        $order->loadMissing('articles.article');
        $orderLines = $order->articles->keyBy('article_id');
        $currentInvoiceByArticle = $editingInvoice && $editingInvoice->order_no === $order->order_no
            ? $editingInvoice->invoiceArticles()->selectRaw('article_id, sum(invoice_pcs) as pcs')->groupBy('article_id')->pluck('pcs', 'article_id')
            : collect();

        foreach ($lines->groupBy('article_id') as $articleId => $articleLines) {
            $orderArticle = $orderLines->get((int) $articleId);
            $invoicePcs = (int) $articleLines->sum('invoice_pcs');

            if (!$orderArticle) {
                $articleNo = Article::find((int) $articleId)?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles_in_invoice' => "Article {$articleNo} is not in selected order.",
                ]);
            }

            $alreadyDispatched = (int) ($orderArticle->dispatched_pcs ?? 0);
            $currentInvoicePcs = (int) ($currentInvoiceByArticle->get((int) $articleId) ?? 0);
            $invoiceablePcs = max(0, (int) $orderArticle->ordered_pcs - $alreadyDispatched + $currentInvoicePcs);

            if ($invoicePcs > $invoiceablePcs) {
                $articleNo = $orderArticle->article?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles_in_invoice' => "Invoice quantity for {$articleNo} cannot exceed invoiceable quantity. Available: {$invoiceablePcs} pcs.",
                ]);
            }
        }
    }

    public function replaceInvoiceArticles(Invoice $invoice, Collection $lines): void
    {
        $invoice->invoiceArticles()->delete();

        foreach ($lines as $line) {
            $invoice->invoiceArticles()->create([
                'article_id' => $line['article_id'],
                'description' => $line['description'],
                'invoice_pcs' => $line['invoice_pcs'],
            ]);
        }
    }

    public function recalculateOrderDispatch(Order|string|null $order): void
    {
        $order = $order instanceof Order
            ? $order
            : Order::where('order_no', $order)->first();

        if (!$order) {
            return;
        }

        $order->unsetRelation('articles');
        $order->load('articles');

        $invoicePcsByArticle = Invoice::query()
            ->where('order_no', $order->order_no)
            ->join('invoice_articles', 'invoice_articles.invoice_id', '=', 'invoices.id')
            ->selectRaw('invoice_articles.article_id, sum(invoice_articles.invoice_pcs) as pcs')
            ->groupBy('invoice_articles.article_id')
            ->pluck('pcs', 'invoice_articles.article_id');

        foreach ($order->articles as $orderArticle) {
            $orderArticle->update([
                'dispatched_pcs' => (int) ($invoicePcsByArticle->get($orderArticle->article_id) ?? 0),
            ]);
        }

        $order->refresh()->load('articles');
        $orderedPcs = (int) $order->articles->sum('ordered_pcs');
        $dispatchedPcs = (int) $order->articles->sum('dispatched_pcs');
        $order->status = $dispatchedPcs <= 0
            ? 'pending'
            : ($dispatchedPcs < $orderedPcs ? 'partially_invoiced' : 'invoiced');
        $order->save();
    }

    public function invoicedPcsByArticle(Order $order): Collection
    {
        return Invoice::query()
            ->where('order_no', $order->order_no)
            ->join('invoice_articles', 'invoice_articles.invoice_id', '=', 'invoices.id')
            ->selectRaw('invoice_articles.article_id, sum(invoice_articles.invoice_pcs) as pcs')
            ->groupBy('invoice_articles.article_id')
            ->pluck('pcs', 'invoice_articles.article_id')
            ->map(fn ($pcs) => (int) $pcs);
    }
}
