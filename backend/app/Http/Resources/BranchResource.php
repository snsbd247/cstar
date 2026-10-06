<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'slug' => $this->slug,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'map_url' => $this->map_url,
            'opening_hours' => $this->opening_hours,
            'is_active' => $this->is_active,
            'show_on_website' => $this->show_on_website,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->whenPivotLoaded('branch_user', fn () => (bool) $this->pivot->is_primary),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
