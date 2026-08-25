<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InventoryItem extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'branch_id',
        'name',
        'type',
        'unit',
        'tag',
        'fabric_id',
        'color',
        'is_active',
        'remarks',
        'creator_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->creator_id)) {
                $model->creator_id = Auth::id();
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function fabric()
    {
        return $this->belongsTo(Setup::class, 'fabric_id');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function getStockQuantityAttribute(): float
    {
        $in = (float) $this->transactions()->where('direction', 'in')->sum('quantity');
        $out = (float) $this->transactions()->where('direction', 'out')->sum('quantity');

        return $in - $out;
    }

    public function scopeApplyModelFilters($query, string $key, mixed $value)
    {
        if ($key === 'supplier_name') {
            return $query->whereHas('transactions.supplier', function ($supplierQuery) use ($value) {
                $supplierQuery->where('supplier_name', 'like', "%{$value}%");
            });
        }

        if ($key === 'date' && is_array($value) && isset($value['start'], $value['end'])) {
            return $query->whereHas('transactions', function ($transactionQuery) use ($value) {
                $transactionQuery->whereBetween('date', [$value['start'], $value['end']]);
            });
        }

        if (in_array($key, ['name', 'type', 'tag', 'fabric_id', 'unit', 'is_active'], true)) {
            return $query->where($key, 'like', "%{$value}%");
        }

        return $query;
    }

    public function toFormattedArray(): array
    {
        $transactions = $this->transactions->sortByDesc('id')->values();
        $purchase = $transactions->first(fn ($row) => $row->direction === 'in' && $row->supplier_id)
            ?? $transactions->firstWhere('direction', 'in');
        $currentStock = max(0, $this->stock_quantity);
        $supplierBalances = $transactions
            ->filter(fn ($row) => $row->supplier_id !== null)
            ->groupBy('supplier_id')
            ->map(function ($rows, $supplierId) use ($currentStock) {
                $received = (float) $rows->where('direction', 'in')->sum('quantity');
                $returned = (float) $rows->where('direction', 'out')->sum('quantity');
                $available = min(max(0, $received - $returned), $currentStock);
                $latestReceived = $rows->where('direction', 'in')->sortByDesc('id')->first();

                return [
                    'supplier_id' => (int) $supplierId,
                    'supplier_name' => $latestReceived?->supplier?->supplier_name ?? $rows->first()?->supplier?->supplier_name ?? '-',
                    'received_quantity' => $received,
                    'returned_quantity' => $returned,
                    'available_quantity' => $available,
                    'unit_price' => $latestReceived?->unit_price,
                    'payment_method' => $latestReceived?->payment_method,
                ];
            })
            ->filter(fn (array $balance) => $balance['available_quantity'] > 0)
            ->values()
            ->all();
        return [
            'id' => $this->id,
            'date' => $purchase?->date?->format('d-M-Y, D') ?? $this->created_at?->format('d-M-Y, D') ?? '-',
            'filter_date' => $purchase?->date?->toDateString() ?? $this->created_at?->toDateString(),
            'name' => $this->name,
            'item_type' => ucfirst(str_replace('_', ' ', $this->type)),
            'type' => ucfirst(str_replace('_', ' ', $this->type)),
            'unit' => $this->unit ?? '-',
            'tag' => $this->tag ?? '-',
            'fabric' => $this->fabric?->title ?? '-',
            'color' => $this->color ?? '-',
            'stock_quantity' => $this->stock_quantity,
            'stock_quantity_formatted' => rtrim(rtrim(number_format($this->stock_quantity, 3), '0'), '.'),
            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'remarks' => $this->remarks ?? '-',
            'supplier_name' => $purchase?->supplier?->supplier_name ?? '-',
            'unit_price' => $purchase?->unit_price,
            'amount' => $purchase?->amount,
            'supplier_balances' => $supplierBalances,
            'transaction_history' => $transactions->map(function ($transaction) {
                $production = $transaction->source instanceof \App\Models\Production ? $transaction->source : null;
                $production?->loadMissing(['article', 'worker']);
                $movementType = match (true) {
                    $transaction->direction === 'out' && $production !== null => 'Issued to Production',
                    $transaction->direction === 'out' && $transaction->supplier_id !== null => 'Returned to Supplier',
                    $transaction->direction === 'in' => 'Received from Supplier',
                    default => ucfirst($transaction->direction),
                };
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date?->format('d-M-Y'),
                    'date_raw' => $transaction->date?->toDateString(),
                    'direction' => $transaction->direction,
                    'type' => $movementType,
                    'quantity' => $transaction->quantity,
                    'unit' => $transaction->unit ?: $this->unit,
                    'supplier' => $transaction->supplier?->supplier_name ?? '-',
                    'rate' => $transaction->unit_price,
                    'amount' => $transaction->amount,
                    'reference' => $transaction->reference_no ?: '-',
                    'article' => $production?->article?->article_no ?? '-',
                    'worker' => $production?->worker?->employee_name ?? '-',
                    'remarks' => $transaction->remarks ?: '-',
                ];
            })->all(),
            'onclick' => 'generateModal(this)',
            'oncontextmenu' => 'generateContextMenu(event)',
            'data' => [
                'id' => $this->id,
                'name' => $this->name,
                'type' => $this->type,
                'unit' => $this->unit,
                'tag' => $this->tag,
                'fabric' => $this->fabric?->title,
                'color' => $this->color,
                'stock_quantity' => $this->stock_quantity,
                'is_active' => $this->is_active,
                'remarks' => $this->remarks,
            ],
        ];
    }
}
