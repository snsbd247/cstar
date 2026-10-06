<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'image_path', 'category', 'consent_confirmed', 'is_published', 'sort_order'])]
class GalleryItem extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'consent_confirmed' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /** Only published photos whose photo/media consent was confirmed reach the website. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('consent_confirmed', true)->orderBy('sort_order')->latest('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
