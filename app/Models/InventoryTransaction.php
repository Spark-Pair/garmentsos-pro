<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'inventory_item_id',
        'direction',
        'date',
        'supplier_id',
        'payment_method',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'source_type',
        'source_id',
        'reference_no',
        'remarks',
        'creator_id',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->creator_id)) {
                $model->creator_id = Auth::id();
            }
        });
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function toFormattedArray(): array
    {
        return [
            'id' => $this->id,
            'date' => optional($this->date)->format('Y-m-d'),
            'supplier_name' => $this->supplier?->supplier_name ?? '-',
            'item_name' => $this->item?->name ?? '-',
            'direction' => ucfirst($this->direction),
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'reference_no' => $this->reference_no,
            'remarks' => $this->remarks,
        ];
    }
}
