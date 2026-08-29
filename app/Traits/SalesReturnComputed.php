<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SalesReturnComputed
{
    use SearchFilterHelpers;

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'customer' => $this->invoice->customer->customer_name . ' | ' . $this->invoice->customer->city->title,
            'article_no' => $this->article->article_no,
            'invoice_no' => $this->invoice->invoice_no,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'amount' => \App\Support\Money::format($this->amount),
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
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
