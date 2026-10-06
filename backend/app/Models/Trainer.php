<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Physical/functional training staff. Not a therapist (TRAINER ≠ THERAPIST). */
#[Fillable([
    'user_id', 'branch_id', 'employee_code', 'name', 'slug', 'phone', 'email',
    'qualification', 'experience_years', 'bio', 'status', 'show_on_website',
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
