<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait InvoiceComputed
{
    private function invoiceSearchTerms($value)
    {
        return collect(preg_split('/[,\r\n]+/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->flatMap(function (string $item) {
                $prefix = '';
                if (preg_match('/^(\d+)\s*(?:-|to|se)\s*(\d+)$/i', $item, $matches)) {
                    $startText = $matches[1];
                    $endText = $matches[2];
                } elseif (preg_match('/^(.+?)(\d+)\s*(?:-|to|se)\s*(?:.+?)(\d+)$/i', $item, $matches)) {
                    $prefix = preg_replace('/\d+\s*$/', '', $matches[1]);
                    $startText = $matches[2];
                    $endText = $matches[3];
                } else {
                    return [$item];
                }

                $start = (int) $startText;
                $end = (int) $endText;
                $width = max(strlen($startText), strlen($endText));
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                if (($end - $start) > 1000) {
                    return [$item];
                }

                return collect(range($start, $end))
                    ->map(fn ($number) => $prefix . str_pad((string) $number, $width, '0', STR_PAD_LEFT))
                    ->all();
            })
            ->unique(fn ($item) => strtolower($item))
            ->values();
    }

    private function invoiceRangeBounds($value): ?array
    {
        $value = trim((string) $value);
        $prefix = '';
        if (preg_match('/^(\d+)\s*(?:-|to|se)\s*(\d+)$/i', $value, $matches)) {
            $startText = $matches[1];
            $endText = $matches[2];
        } elseif (preg_match('/^(.+?)(\d+)\s*(?:-|to|se)\s*(?:.+?)(\d+)$/i', $value, $matches)) {
            $prefix = preg_replace('/\d+\s*$/', '', $matches[1]);
            $startText = $matches[2];
            $endText = $matches[3];
        } else {
            return null;
        }

        $start = (int) $startText;
        $end = (int) $endText;
        $width = max(strlen($startText), strlen($endText));
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, $width, $prefix];
    }

    private function invoiceIdsForLargeRange($value)
    {
        $bounds = $this->invoiceRangeBounds($value);
        if (!$bounds) {
            return null;
        }

        [$start, $end, $width, $prefix] = $bounds;
        if (($end - $start) < 200) {
            return null;
        }

        return \App\Models\Invoice::query()
            ->get(['id', 'invoice_no'])
            ->filter(function ($invoice) use ($start, $end, $width, $prefix) {
                $invoiceNo = (string) $invoice->invoice_no;
                if ($prefix !== '' && !str_starts_with($invoiceNo, $prefix)) {
                    return false;
                }

                preg_match_all('/\d+/', $invoiceNo, $matches);
                $lastNumber = collect($matches[0] ?? [])->last();
                if ($lastNumber === null || strlen($lastNumber) !== $width) {
                    return false;
                }

                $number = (int) $lastNumber;
                return $number >= $start && $number <= $end;
            })
            ->pluck('id')
            ->values();
    }

    public function toFormattedArray()
    {
        $invoiceArticles = $this->invoiceArticles
            ? $this->invoiceArticles->map(fn($invoiceArticle) => [
                'id' => $invoiceArticle->id,
                'description' => $invoiceArticle->description,
                'invoice_pcs' => (int) ($invoiceArticle->invoice_pcs ?? 0),
                'returned_pcs' => (int) $this->salesReturns
                    ->where('type', 'return')
                    ->where('article_id', $invoiceArticle->article_id)
                    ->sum('quantity'),
                'adjusted_pcs' => (int) $this->salesReturns
                    ->where('type', 'adjustment')
                    ->where('article_id', $invoiceArticle->article_id)
                    ->sum('quantity'),
                'ordered_pcs' => (int) ($invoiceArticle->ordered_pcs ?? 0),
                'shipment_pcs' => (int) ($invoiceArticle->shipment_pcs ?? 0),
                'article' => $invoiceArticle->article ? [
                    'id' => $invoiceArticle->article->id,
                    'article_no' => $invoiceArticle->article->article_no,
                    'description' => $invoiceArticle->article->description,
                    'fabric_type' => $invoiceArticle->article->fabric_type,
                    'pcs_per_packet' => $invoiceArticle->article->pcs_per_packet,
                    'sales_rate' => $invoiceArticle->article->sales_rate,
                ] : null,
            ])->values()
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->invoice_no,
            'details' => [
                'Customer' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount' => \App\Support\Money::format($this->netAmount),
                'Reff. No.' => $this->order_no ?? $this->shipment_no,
            ],
            'data' => [
                'id' => $this->id,
                'invoice_no' => $this->invoice_no,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('invoices', $this),
                'order_no' => $this->order_no,
                'shipment_no' => $this->shipment_no,
                'deliver_to' => $this->order?->deliver_to,
                'date' => $this->date?->format('Y-m-d'),
                'netAmount' => (float) ($this->netAmount ?? 0),
                'carton_count' => (int) ($this->carton_count ?? 0),
                'customer' => $this->customer ? [
                    'id' => $this->customer->id,
                    'customer_name' => $this->customer->customer_name,
                    'urdu_title' => $this->customer->urdu_title,
                    'address' => $this->customer->address,
                    'phone_number' => $this->customer->phone_number,
                    'city' => $this->customer->city ? [
                        'id' => $this->customer->city->id,
                        'title' => $this->customer->city->title,
                        'short_title' => $this->customer->city->short_title,
                    ] : null,
                ] : null,
                'order' => $this->order ? [
                    'id' => $this->order->id,
                    'order_no' => $this->order->order_no,
                    'deliver_to' => $this->order->deliver_to,
                    'discount' => (float) ($this->order->discount ?? 0),
                    'netAmount' => (float) ($this->order->netAmount ?? 0),
                ] : null,
                'shipment' => $this->shipment ? [
                    'id' => $this->shipment->id,
                    'shipment_no' => $this->shipment->shipment_no,
                    'discount' => (float) ($this->shipment->discount ?? 0),
                    'netAmount' => (float) ($this->shipment->netAmount ?? 0),
                ] : null,
                'invoice_articles' => $invoiceArticles,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'invoice_no':
                $rangeInvoiceIds = $this->invoiceIdsForLargeRange($value);
                if ($rangeInvoiceIds !== null) {
                    return $rangeInvoiceIds->isEmpty()
                        ? $query->whereRaw('1 = 0')
                        : $query->whereIn('id', $rangeInvoiceIds->all());
                }

                $invoiceNumbers = $this->invoiceSearchTerms($value);

                if ($invoiceNumbers->isEmpty()) {
                    return $query->where('invoice_no', 'like', "%$value%");
                }

                return $query->where(function ($invoiceQuery) use ($invoiceNumbers) {
                    foreach ($invoiceNumbers as $invoiceNo) {
                        $invoiceQuery->orWhere('invoice_no', 'like', "%{$invoiceNo}%");
                    }
                });

            case 'customer_name':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%$value%")
                    ->orWhereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });

            case 'reff_no':
                return $query->where('order_no', 'like', "%$value%")->orWhere('shipment_no', 'like', "%$value%");

            case 'city':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->whereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;

                \App\Support\DateRange::apply($query, 'date', $start, $end);
                return $query;

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
