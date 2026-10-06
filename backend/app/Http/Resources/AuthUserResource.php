<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The logged-in user, including the full permission list the React app uses
 * to show/hide menus. The API still enforces every permission server-side.
 */
class AuthUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_super_admin' => $this->isSuperAdmin(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
