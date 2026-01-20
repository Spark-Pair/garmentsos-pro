<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\ReturnFabricComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnFabric extends Model
{
    use HasFactory;

    use Filterable, ReturnFabricComputed;

    protected $fillable = [
        'worker_id',
        'date',
        'tag',
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
