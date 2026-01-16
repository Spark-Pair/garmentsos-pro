<?php

namespace App\Models;

use App\Traits\CRComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CR extends Model
{
    use HasFactory;

    use Filterable, CRComputed;

    protected $fillable = [
        'date',
        'c_r_no',
        'voucher_id',
        'return_payments',
        'new_payments',
    ];

    protected $casts = [
        'date' => 'date',
        'return_payments' => 'array',
        'new_payments' => 'array',
    ];

    public function voucher() {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function payments() {
        return $this->hasMany(SupplierPayment::class, 'c_r_id');
    }
}
