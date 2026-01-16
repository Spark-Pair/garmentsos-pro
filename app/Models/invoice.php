<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\InvoiceComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use HasFactory;

    use Filterable, InvoiceComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "invoice_no",
        "order_no",
        "shipment_no",
        "customer_id",
        "date",
        "netAmount",
        "cotton_count",
        "cargo_name",
    ];

    protected $casts = [
        'date' => 'datetime',
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

    protected $appends = ['is_in_cargo'];
    public function order() {
        return $this->belongsTo(Order::class, 'order_no', 'order_no');
    }

    public function shipment() {
        return $this->belongsTo(Shipment::class, 'shipment_no', 'shipment_no');
    }

    public function customer() {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function bilty() {
        return $this->hasOne(Bilty::class);
    }

    public function getIsInCargoAttribute()
    {
        $cargos = Cargo::all();

        foreach ($cargos as $cargo) {
            $invoices = json_decode($cargo->invoices_array, true);

            if (!is_array($invoices)) continue;

            foreach ($invoices as $invoice) {
                if (isset($invoice['id']) && $invoice['id'] == $this->id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function salesReturns() {
        return $this->hasMany(SalesReturn::class, 'invoice_id');
    }
}
