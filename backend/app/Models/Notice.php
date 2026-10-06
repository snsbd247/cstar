<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'body', 'audience', 'show_on_website', 'is_published', 'publish_at', 'expires_at', 'created_by'])]
class Notice extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'show_on_website' => 'boolean',
            'is_published' => 'boolean',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Published, public and inside its publish window. */
    public function scopeOnWebsite(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where('show_on_website', true)
            ->where('audience', 'all')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('publish_at')->latest('id');
    }
}
