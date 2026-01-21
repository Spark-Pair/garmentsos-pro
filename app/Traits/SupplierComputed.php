<?php

namespace App\Traits;

use App\Models\Setup;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SupplierComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'image' => $this->user->profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : '/storage/uploads/images/' . $this->user->profile_picture,
            'name' => $this->supplier_name,
            'details' => [
                'Urdu Title' => $this->urdu_title,
                'Phone' => $this->phone_number,
                'Balance' => $this->balance,
            ],
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'status' => $this->user->status,
            ],
            'date' => $this->date,
            'data'=> $this,
            'categories'=> $this->Categories,
            'profile'=> true,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'username':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('username', 'like', '%' . $value . '%');
                });

            case 'category':
                $categoryId = Setup::where('id', 'like', '%' . $value . '%')
                                    ->value('id');

                if ($categoryId) {
                    return $query->whereJsonContains('categories_array', $categoryId);
                }

                return $query;

            case 'status':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('status', 'like', '%' . $value . '%');
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
