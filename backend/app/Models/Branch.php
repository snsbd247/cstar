<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'name', 'name_bn', 'slug', 'address', 'phone', 'email', 'map_url',
    'opening_hours', 'is_active', 'show_on_website', 'sort_order',
])]
class Branch extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'show_on_website' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_primary');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /** Restrict a query to the branches the given user may see. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleBranchIds();

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }
}
