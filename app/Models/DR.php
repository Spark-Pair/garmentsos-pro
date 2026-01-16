<?php

namespace App\Models;

use App\Traits\DRComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DR extends Model
{
    use HasFactory;

    use Filterable, DRComputed;

    protected $fillable = [
        'd_r_no',
        'customer_id',
        'date',
        'return_payments',
        'new_payments',
    ];

    protected $casts = [
        'date' => 'date',
        'return_payments' => 'array',
        'new_payments' => 'array',
    ];

    public function customer() {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
