<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SalesReturnComputed
{
    use SearchFilterHelpers;

    public function toFormattedArray()
    {
        $customer = $this->invoice?->customer;
        $invoiceArticle = $this->invoice?->invoiceArticles?->firstWhere('article_id', $this->article_id);
        $description = trim((string) ($invoiceArticle?->description ?: $this->article?->description ?: '-'));
        $customerName = trim(($customer?->customer_name ?? '-') . ' | ' . ($customer?->city?->title ?? '-'), ' |');
        $returnNo = $this->return_no ?: ('LEGACY-SR-' . optional($this->date)->format('Ymd'));

        return [
            'id' => $this->id,
            'return_no' => $returnNo,
            'date' => $this->date->format('d-M-Y, D'),
            'customer' => $customerName,
            'article_no' => $this->article->article_no,
            'invoice_no' => $this->invoice->invoice_no,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'amount' => \App\Support\Money::format($this->amount),
            'line_count' => 1,
            'data' => [
                'return_no' => $returnNo,
                'date' => $this->date?->format('Y-m-d'),
                'type' => $this->type,
                'branch_id' => $this->branch_id,
                'branch_branding' => app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('sales_returns', $this),
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'customer_name' => $customer->customer_name,
                    'urdu_title' => $customer->urdu_title,
                    'address' => $customer->address,
                    'phone_number' => $customer->phone_number,
                    'city' => $customer->city ? [
                        'id' => $customer->city->id,
                        'title' => $customer->city->title,
                        'short_title' => $customer->city->short_title,
                    ] : null,
                ] : null,
                'articles' => [[
                    'article' => $this->article ? [
                        'id' => $this->article->id,
                        'article_no' => $this->article->article_no,
                        'description' => $this->article->description,
                        'pcs_per_packet' => $this->article->pcs_per_packet,
                        'sales_rate' => $this->article->sales_rate,
                    ] : null,
                    'article_no' => $this->article?->article_no,
                    'description' => $description,
                    'invoice_no' => $this->invoice?->invoice_no,
                    'quantity' => (int) $this->quantity,
                    'sales_rate' => (float) ($this->article?->sales_rate ?? 0),
                    'amount' => (float) $this->amount,
                    'customer' => $customerName,
                    'type' => $this->type,
                ]],
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'return_no':
                return $query->where('return_no', 'like', "%{$value}%");

            case 'id':
                $tokens = $this->searchFilterTokens($value)
                    ->filter(fn ($token) => ctype_digit((string) $token)
                        || $this->searchFilterRangeBounds((string) $token));

                if ($tokens->isEmpty()) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where(function ($idQuery) use ($tokens) {
                    foreach ($tokens as $token) {
                        $bounds = $this->searchFilterRangeBounds((string) $token);
                        if ($bounds) {
                            $idQuery->orWhereBetween('id', [$bounds[0], $bounds[1]]);
                        } elseif (ctype_digit((string) $token)) {
                            $idQuery->orWhere('id', (int) $token);
                        }
                    }
                });

            case 'customer':
                return $query->whereHas('invoice', function ($q) use ($value) {
                    $q->whereHas('customer', function ($q2) use ($value) {
                        $q2->where('customer_name', 'like', "%{$value}%")
                        ->orWhereHas('city', function ($q3) use ($value) {
                            $q3->where('title', 'like', "%{$value}%");
                        });
                    });
                });

            case 'article_no':
                return $query->whereHas('article', function ($query) use ($value) {
                    $query->where('article_no', 'like', "%{$value}%");
                });

            case 'invoice_no':
                return $query->whereHas('invoice', function ($query) use ($value) {
                    $query->where('invoice_no', 'like', "%{$value}%");
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
