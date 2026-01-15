<?php

namespace App\Traits;

use App\Models\Setup;

trait CustomerComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'image' => $this->user->profile_picture,
            'name' => $this->customer_name,
            'details' => [
                'Urdu Title' => $this->urdu_title,
                'Category' => $this->category,
                'Balance' => $this->balance,
            ],
            'person_name'=> $this->person_name,
            'phone_number'=> $this->phone_number,
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'status' => $this->user->status,
            ],
            'city' => $this->city->title,
            'date' => $this->date,
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

            case 'city':
                return $query->whereHas('city', function ($q) use ($value) {
                    $q->where('id', $value);
                });

            case 'status':
                return $query->whereHas('user', function ($q) use ($value) {
                    $q->where('status', $value);
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
