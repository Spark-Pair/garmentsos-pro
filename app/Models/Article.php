<?php

namespace App\Models;

use App\Traits\ArticleComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    use Filterable, ArticleComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'article_no',
        'date',
        'category',
        'size',
        'season',
        'quantity',
        'extra_pcs',
        'fabric_type',
        'sales_rate',
        'rates_array',
        'pcs_per_packet',
        'processed_by',
        'image',
    ];

    protected $casts = [
        'rates_array' => 'json',
        'date' => 'date',
    ];

    protected $appends = [
        'ordered_quantity',
        'sold_quantity',
    ];

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString(); // 'Y-m-d'
    }

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function physicalQuantity()
    {
        return $this->hasMany(physicalQuantity::class, 'article_id');
    }

    public function production()
    {
        return $this->hasMany(Production::class, 'article_id');
    }

    public function shipmentArticles()
    {
        return $this->hasMany(ShipmentArticles::class, 'article_id');
    }

    public function orderArticles()
    {
        return $this->hasMany(OrderArticles::class, 'article_id');
    }
}
