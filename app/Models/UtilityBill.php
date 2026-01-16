<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\UtilityBillComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UtilityBill extends Model
{
    use HasFactory;

    use Filterable, UtilityBillComputed;

    protected $fillable = [
        'account_id',
        'month',
        'units',
        'amount',
        'due_date',
        'is_paid',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_paid' => 'boolean',
        'month' => 'date'
    ];

    public function account() {
        return $this->belongsTo(UtilityAccount::class, 'account_id');
    }
}
