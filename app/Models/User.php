<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\UserComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    use Filterable, UserComputed;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $hidden = [
        'created_at',
        'updated_at',
        'password',
    ];

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'status',
        'profile_picture',
        'theme',
        'layout',
        'invoice_type',
        'voucher_type',
        'production_type',
        'daily_ledger_type',
        'c_r_type',
        'menu_shortcuts',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */

    public function customer() {
        return $this->hasOne(Customer::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }
}
