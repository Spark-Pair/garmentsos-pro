<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppPermissionRule extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'module_key',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_override',
        'can_switch',
        'can_manage',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_override' => 'boolean',
        'can_switch' => 'boolean',
        'can_manage' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
