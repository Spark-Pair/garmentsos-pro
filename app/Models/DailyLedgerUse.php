<?php

namespace App\Models;

use App\Traits\DailyLedgerUseComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyLedgerUse extends Model
{
    use HasFactory;

    use Filterable, DailyLedgerUseComputed;

    protected $fillable = [
        'date',
        'case',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
