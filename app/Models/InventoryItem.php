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

    public function toFormattedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => ucfirst(str_replace('_', ' ', $this->type)),
            'unit' => $this->unit ?? '-',
            'tag' => $this->tag ?? '-',
            'fabric' => $this->fabric?->title ?? '-',
            'color' => $this->color ?? '-',
            'stock_quantity' => $this->stock_quantity,
            'stock_quantity_formatted' => rtrim(rtrim(number_format($this->stock_quantity, 3), '0'), '.'),
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'remarks' => $this->remarks ?? '-',
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
