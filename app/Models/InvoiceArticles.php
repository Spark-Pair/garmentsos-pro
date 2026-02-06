<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceArticles extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'article_id',
        'description',
        'invoice_pcs',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
