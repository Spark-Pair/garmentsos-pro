<?php

namespace App\Models;

use App\Traits\DailyLedgerDepositComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyLedgerDeposit extends Model
{
    use HasFactory;

    use Filterable, DailyLedgerDepositComputed;

    protected $fillable = [
        'date',
        'method',
        'amount',
        'reff_no',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
