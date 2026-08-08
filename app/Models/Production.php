<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\ProductionComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Production extends Model
{
    use HasFactory;

    use Filterable, ProductionComputed;

    protected $fillable = [
        'issue_date',
        'branch_id',
        'receive_date',
        'article_id',
        'work_id',
        'worker_id',
        'supplier_id',
        'tags',
        'materials',
        'parts',
        'title',
        'rate',
        'ticket',
        'amount',
        'creator_id',
        'issued_by_name',
        'received_by_name',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'receive_date' => 'date',
        'tags' => 'array',
        'materials' => 'array',
        'parts' => 'array',
        'rate' => 'float',
        'amount' => 'float',
    ];

    protected $hidden = [
        'article_id',
        'creator_id',
        'created_at',
        'updated_at',
    ];

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

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }

    public function work()
    {
        return $this->belongsTo(Setup::class, 'work_id', 'id');
    }

    public function worker()
    {
        return $this->belongsTo(Employee::class, 'worker_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function productionTags()
    {
        return $this->hasMany(ProductionTag::class);
    }

    public function productionMaterials()
    {
        return $this->hasMany(ProductionMaterial::class);
    }

    public function productionFlows()
    {
        return $this->hasMany(ProductionFlow::class);
    }
}
