<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierPayment extends Model
{
    use HasFactory;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "supplier_id",
        "date",
        "method",
        "amount",
        "reff_no",
        "cheque_no",
        "cheque_id",
        "slip_id",
        "program_id",
        "bank_account_id",
        "self_account_id",
        "transaction_id",
        "remarks",
        "voucher_id",
        "is_return",
        "c_r_id",
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
        // static::addGlobalScope('withCreator', function (builder $builder) {
        //     $builder->with('creator');
        // });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, "supplier_id");
    }

    public function program()
    {
        return $this->belongsTo(PaymentProgram::class, "program_id");
    }

    public function cheque()
    {
        return $this->belongsTo(CustomerPayment::class, "cheque_id");
    }

    public function slip()
    {
        return $this->belongsTo(CustomerPayment::class, "slip_id");
    }

    public function voucher() {
        return $this->belongsTo(Voucher::class, "voucher_id");
    }

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function selfAccount() {
        return $this->belongsTo(BankAccount::class, 'self_account_id');
    }

    public function cr() {
        return $this->belongsTo(CR::class, 'c_r_id');
    }
}
