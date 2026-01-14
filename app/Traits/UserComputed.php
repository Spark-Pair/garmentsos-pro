<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait UserComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'uId' => $this->id,
            'name' => $this->name,
            'details' => [
                'Username' => $this->username,
                'Role' => $this->role,
            ],
            'status' => $this->status,
            'image' => $this->profile_picture,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
