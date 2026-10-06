<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    public function schedules(): HasMany
    {
        return $this->hasMany(TrainingGroupSchedule::class)->orderBy('weekday');
    }

    public function scheduleFor(Carbon $date): ?TrainingGroupSchedule
    {
        return $this->schedules->firstWhere('weekday', $date->dayOfWeek);
    }

    public function isHoliday(Carbon $date): bool
    {
        return Holiday::whereDate('date', $date)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $this->branch_id))
            ->exists();
    }

    /** Students on the roster on a given day: active enrollments that had started and not ended. */
    public function rosterOn(Carbon $date): Builder
    {
        return Enrollment::query()
            ->where('type', EnrollmentType::Training)
            ->where('status', EnrollmentStatus::Active)
            ->whereDate('start_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->whereHas('trainingEnrollment', fn ($t) => $t->where('training_group_id', $this->id));
    }

    /** Trainer leads the class or is the assigned trainer of one of its open enrollments. */
    public function isTaughtBy(?Trainer $trainer): bool
    {
        if (! $trainer) {
            return false;
        }

        return $this->lead_trainer_id === $trainer->id
            || $this->trainingEnrollments()->where('trainer_id', $trainer->id)
                ->whereHas('enrollment', fn ($e) => $e->whereIn('status', EnrollmentStatus::open()))->exists();
    }
}
