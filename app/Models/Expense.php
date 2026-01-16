<?php

namespace App\Models;

use App\Traits\ExpenseComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    use Filterable, ExpenseComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'date',
        'supplier_id',
        'expense',
        'reff_no',
        'amount',
        'lot_no',
        'remarks'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenseSetups()
    {
        return $this->belongsTo(Setup::class, 'expense')->where('type', 'supplier_category');
    }
}
