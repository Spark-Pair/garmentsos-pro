<?php

namespace App\Models;

use App\Traits\CargoComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Cargo extends Model
{
    use HasFactory;

    use Filterable, CargoComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "cargo_no",
        "date",
        "cargo_name",
        "invoices_array",
    ];

    protected $casts = [
        'date' => 'date',
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

    protected $appends = ['invoices'];
    public function getInvoicesAttribute()
    {
        $RawInvoices = json_decode($this->invoices_array, true);

        if (!is_array($RawInvoices)) return [];

        $invoices = [];

        foreach ($RawInvoices as $RawInvoice) {
            $invoice = Invoice::with('customer.city')->where('id', $RawInvoice['id'])->first();

            if ($invoice) {
                $invoices[] = $invoice;
            }
        }

        return $invoices;
    }
}
