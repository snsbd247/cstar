<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A "Class" in the UI. The roster is its current training enrollments (no separate class_students table). */
#[Fillable([
    'branch_id', 'lead_trainer_id', 'room_id', 'code', 'name', 'max_students',
    'start_date', 'end_date', 'status', 'notes',
])]
class TrainingGroup extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function leadTrainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'lead_trainer_id');
    }

    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    /** Students counted against max_students (pending ones reserve a seat too). */
    public function occupiedSeats(): int
    {
        return $this->trainingEnrollments()
            ->whereHas('enrollment', fn ($q) => $q->whereIn('status', EnrollmentStatus::open()))
            ->count();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
