<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\IssuedFabricComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssuedFabric extends Model
{
    use HasFactory;

    use Filterable, IssuedFabricComputed;

    protected $fillable = [
        'date',
        'tag',
        'worker_id',
        'quantity',
        'remarks',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function worker() {
        return $this->belongsTo(Employee::class, 'worker_id');
    }
}
