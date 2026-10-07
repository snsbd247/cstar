<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primary = $this->primaryRole();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_type' => $this->user_type,
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
            'roles' => $this->getRoleNames(),
            'primary_role' => $primary?->value,
            'primary_role_label' => $primary?->label(),
            'home_path' => $this->homePath(),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
