<?php

namespace App\Models;

use App\Traits\FabricComputed;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fabric extends Model
{
    use HasFactory;

    use Filterable, FabricComputed;

    protected $fillable = [
        'date',
        'supplier_id',
        'fabric_id',
        'color',
        'unit',
        'quantity',
        'reff_no',
        'remarks',
        'tag'
    ];

    protected $hidden = [
        'supplier_id',
        'fabric_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function fabric()
    {
        return $this->belongsTo(Setup::class, 'fabric_id');
    }
}
