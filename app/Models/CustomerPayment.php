<?php

namespace App\Models;

use App\Traits\CustomerPaymentComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CustomerPayment extends Model
{
    use HasFactory;

    use Filterable, CustomerPaymentComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "customer_id",
        "date",
        "type",
        "method",
        "amount",
        "cheque_no",
        "slip_no",
        "reff_no",
        "transaction_id",
        "cheque_date",
        "slip_date",
        "clear_date",
        "bank_id",
        "remarks",
        "program_id",
        "bank_account_id",
        "is_return",
        "d_r_id",
    ];

    protected $casts = [
        'date' => 'date',
        'cheque_date' => 'date',
        'slip_date' => 'date',
        'clear_date' => 'date',
        'is_return' => 'boolean',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, "customer_id");
    }

    public function program()
    {
        return $this->belongsTo(PaymentProgram::class, "program_id");
    }

    public function bank()
    {
        return $this->belongsTo(Setup::class, "bank_id");
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, "bank_account_id");
    }

    public function cheque()
    {
        return $this->hasOne(SupplierPayment::class, "cheque_id")->where('is_return', false);
    }

    public function slip()
    {
        return $this->hasOne(SupplierPayment::class, "slip_id")->where('is_return', false);
    }

    public function paymentClearRecord()
    {
        return $this->hasMany(PaymentClear::class, "payment_id");
    }

    public function dr()
    {
        return $this->belongsTo(DR::class, "d_r_id");
    }
}
