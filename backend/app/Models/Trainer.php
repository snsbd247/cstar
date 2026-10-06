<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Physical/functional training staff. Not a therapist (TRAINER ≠ THERAPIST). */
#[Fillable([
    'user_id', 'branch_id', 'employee_code', 'name', 'slug', 'phone', 'email',
    'qualification', 'experience_years', 'bio', 'photo_path', 'status', 'show_on_website', 'sort_order',
])]
class Trainer extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['show_on_website' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ledGroups(): HasMany
    {
        return $this->hasMany(TrainingGroup::class, 'lead_trainer_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
