<?php

namespace App\Models;

use App\Enums\TherapistType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Clinical therapy staff. Not a trainer (TRAINER ≠ THERAPIST). */
#[Fillable([
    'user_id', 'primary_branch_id', 'employee_code', 'name', 'slug', 'designation', 'therapist_type',
    'phone', 'email', 'qualification', 'experience_years', 'bio', 'status', 'show_on_website',
])]
class Therapist extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'therapist_type' => TherapistType::class,
            'show_on_website' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function provides(int $serviceId): bool
    {
        return $this->services()->whereKey($serviceId)->exists();
    }
}
