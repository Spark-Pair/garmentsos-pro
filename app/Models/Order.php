<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\OrderComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use HasFactory;

    use Filterable, OrderComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'customer_id',
        'date',
        'discount',
        'netAmount',
        'order_no',
        'status',
    ];

    protected $casts = [
        'date' => 'date'
    ];

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });

        // Always eager load the associated creator
        static::addGlobalScope('withCreator', function (Builder $builder) {
            $builder->with('creator');
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function articles()
    {
        return $this->hasMany(OrderArticles::class, 'order_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'order_no', 'order_no');
    }

    public function paymentPrograms()
    {
        return $this->hasOne(PaymentProgram::class, 'order_no', 'order_no');
    }

    public function customer() {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
