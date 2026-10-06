<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'phone', 'alt_phone', 'email', 'occupation', 'nid', 'address'])]
class Guardian extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const RELATIONSHIPS = ['father', 'mother', 'grandparent', 'sibling', 'uncle', 'aunt', 'other'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class)
            ->withPivot(['relationship', 'is_primary', 'is_emergency_contact', 'can_access_portal'])
            ->withTimestamps();
    }
}
