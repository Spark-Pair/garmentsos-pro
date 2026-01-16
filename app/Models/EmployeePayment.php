<?php

namespace App\Models;

use App\Traits\EmployeePaymentComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayment extends Model
{
    use HasFactory;

    use Filterable, EmployeePaymentComputed;

    protected $fillable = [
        'employee_id',
        'date',
        'method',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
