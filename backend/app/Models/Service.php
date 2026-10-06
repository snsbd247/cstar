<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'category', 'name', 'name_bn', 'slug', 'short_description', 'default_duration_min',
    'default_price', 'is_bookable_online', 'show_on_website', 'is_active', 'sort_order',
])]
class Service extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'default_price' => 'decimal:2',
            'is_bookable_online' => 'boolean',
            'show_on_website' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function therapists(): BelongsToMany
    {
        return $this->belongsToMany(Therapist::class);
    }

    public function scopeTherapy(Builder $query): Builder
    {
        return $query->where('category', ServiceCategory::Therapy);
    }
}
