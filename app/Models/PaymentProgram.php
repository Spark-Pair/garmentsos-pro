<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\PaymentProgramComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentProgram extends Model
{
    use HasFactory;

    use Filterable, PaymentProgramComputed;

    protected $hidden = [
        'updated_at',
    ];

    protected $fillable = ['program_no', 'order_no', 'date', 'customer_id', 'category', 'sub_category', 'amount', 'remarks'];

    protected $casts = [
        'date' => 'date'
    ];

    protected $appends = ['payments', 'balance', 'payment'];

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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_no', 'order_no');
    }

    public function subCategory()
    {
        return $this->morphTo();
    }
    public function customerPayments()
    {
        return $this->hasMany(CustomerPayment::class, 'program_id');
    }
    // Custom accessor to merge both types of payments
    public function getPaymentsAttribute()
    {
        return $this->customerPayments;
    }
    public function getBalanceAttribute()
    {
        $totalpayment = 0;
        foreach ($this['payments'] as $payment) {
            $totalpayment += $payment->amount;
        }
        return $this['amount'] - $totalpayment;
    }
    public function getPaymentAttribute()
    {
        $totalpayment = 0;
        foreach ($this['payments'] as $payment) {
            $totalpayment += $payment->amount;
        }
        return $totalpayment;
    }
    // Custom method to eager load payments along with their bank account and bank relations
    public function scopeWithPaymentDetails($query)
    {
        return $query->with([
            'customerPayments.bankAccount.bank',
            'customerPayments.bankAccount.subCategory',
        ]);
    }
}
