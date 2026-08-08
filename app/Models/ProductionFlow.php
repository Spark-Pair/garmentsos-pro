<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'article_id',
        'work_id',
        'worker_id',
        'branch_id',
        'movement_type',
        'part',
        'quantity',
        'ticket',
        'parent_ticket',
        'date',
    ];

    protected $casts = [
        'quantity' => 'float',
        'date' => 'date',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function work()
    {
        return $this->belongsTo(Setup::class, 'work_id');
    }

    public function worker()
    {
        return $this->belongsTo(Employee::class, 'worker_id');
    }
}
