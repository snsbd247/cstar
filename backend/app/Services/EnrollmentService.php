<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ServiceCategory;
use App\Models\AssessmentRecommendation;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All enrollment business rules live here (Plan §৬, §৩২).
 *
 *  Regular Training: class + trainer required; class must be active, in the same branch and have a free seat;
 *                    a child can be in only one open training enrollment at a time.
 *  Therapy:          therapy service + therapist required; the therapist must be active and provide that service;
 *                    no two open enrollments for the same therapy service.
 */
class EnrollmentService
{
    public function __construct(
        private IdGenerator $ids,
        private TimelineService $timeline,
    ) {}

    public function create(Patient $patient, array $data, User $actor): Enrollment
    {
        return DB::transaction(function () use ($patient, $data, $actor) {
            // Serialise concurrent enrollments for the same child (duplicate/active checks below).
            Patient::whereKey($patient->id)->lockForUpdate()->first();

            $type = EnrollmentType::from($data['type']);
            $startDate = Carbon::parse($data['start_date'] ?? today());
            $status = EnrollmentStatus::from($data['status'] ?? EnrollmentStatus::Active->value);

            $details = $type === EnrollmentType::Training
                ? $this->validateTraining($patient, $data)
                : $this->validateTherapy($patient, $data);

            $enrollment = Enrollment::create([
                'enrollment_code' => $this->ids->next('enrollment', 'ENR'),
                'patient_id' => $patient->id,
                'branch_id' => $data['branch_id'],
                'type' => $type,
                'status' => $status,
                'start_date' => $startDate,
                'notes' => $data['notes'] ?? null,
                'source_assessment_id' => $data['source_assessment_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Acting on an assessment recommendation links it to the new enrollment.
            if (! empty($data['recommendation_id'])) {
                AssessmentRecommendation::whereKey($data['recommendation_id'])
                    ->whereHas('assessment', fn ($q) => $q->where('patient_id', $patient->id))
                    ->update(['enrollment_id' => $enrollment->id]);
            }

            if ($type === EnrollmentType::Training) {
                $enrollment->trainingEnrollment()->create($details);
                $enrollment->assignments()->create([
                    'trainer_id' => $details['trainer_id'],
                    'training_group_id' => $details['training_group_id'],
                    'from_date' => $startDate,
                    'reason' => 'Enrolled',
                    'created_by' => $actor->id,
                ]);
            } else {
                $enrollment->therapyEnrollment()->create($details);
                $enrollment->assignments()->create([
                    'therapist_id' => $details['therapist_id'],
                    'from_date' => $startDate,
                    'reason' => 'Enrolled',
                    'created_by' => $actor->id,
                ]);
            }

            $this->load($enrollment);
            $this->timeline->record($patient, 'enrollment.created', "Enrolled: {$enrollment->summary()}", $enrollment,
                branchId: $enrollment->branch_id, visibility: 'parent');

            return $enrollment;
        });
    }

    /** activate | hold | resume | complete | discontinue */
    public function changeStatus(Enrollment $enrollment, string $action, array $data = []): Enrollment
    {
        [$from, $to] = EnrollmentStatus::transitions()[$action]
            ?? throw ValidationException::withMessages(['action' => 'Unknown action.']);

        if (! in_array($enrollment->status, $from, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot {$action} an enrollment that is {$enrollment->status->value}.",
            ]);
        }

        return DB::transaction(function () use ($enrollment, $action, $to, $data) {
            $ending = in_array($to, [EnrollmentStatus::Completed, EnrollmentStatus::Discontinued], true);
            $endDate = Carbon::parse($data['end_date'] ?? today());

            if ($ending && $endDate->lt($enrollment->start_date)) {
                throw ValidationException::withMessages(['end_date' => 'End date cannot be before the start date.']);
            }

            $enrollment->update([
                'status' => $to,
                'end_date' => $ending ? $endDate : null,
                'end_reason' => $ending ? ($data['end_reason'] ?? null) : null,
                'end_note' => $ending ? ($data['end_note'] ?? null) : null,
            ]);

            if ($ending) {
                $enrollment->assignments()->whereNull('to_date')->update(['to_date' => $endDate]);
            }

            $this->load($enrollment);
            $this->timeline->record($enrollment->patient, "enrollment.{$action}",
                ucfirst(str_replace('_', ' ', $to->value)).": {$enrollment->summary()}", $enrollment,
                description: $data['end_note'] ?? null, branchId: $enrollment->branch_id, visibility: 'parent');

            return $enrollment;
        });
    }

    /**
     * Moves an open enrollment to another class/trainer (training) or therapist (therapy),
     * closing the current assignment so history and access stay correct.
     */
    public function transfer(Enrollment $enrollment, array $data, User $actor): Enrollment
    {
        if (! $enrollment->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Only open enrollments can be transferred.']);
        }

        return DB::transaction(function () use ($enrollment, $data, $actor) {
            $date = Carbon::parse($data['effective_date'] ?? today());
            $previous = $enrollment->isTraining()
                ? $enrollment->trainingEnrollment->only(['training_group_id', 'trainer_id'])
                : $enrollment->therapyEnrollment->only(['therapist_id']);

            if ($enrollment->isTraining()) {
                $groupId = $data['training_group_id'] ?? $previous['training_group_id'];
                $group = $this->trainingGroup($groupId, $enrollment->branch_id, $groupId !== $previous['training_group_id']);
                $trainerId = $data['trainer_id'] ?? ($groupId !== $previous['training_group_id'] ? $group->lead_trainer_id : $previous['trainer_id']);
                $this->activeTrainer($trainerId);
                $changes = ['training_group_id' => $group->id, 'trainer_id' => $trainerId];
                $enrollment->trainingEnrollment->update($changes);
            } else {
                $therapist = $this->activeTherapist($data['therapist_id'] ?? null, $enrollment->therapyEnrollment->service_id);
                $changes = ['therapist_id' => $therapist->id];
                $enrollment->therapyEnrollment->update($changes);
            }

            if ($changes == $previous) {
                throw ValidationException::withMessages(['transfer' => 'Nothing changed — choose a different class, trainer or therapist.']);
            }

            $enrollment->assignments()->whereNull('to_date')->update(['to_date' => $date]);
            $enrollment->assignments()->create([...$changes, 'from_date' => $date, 'reason' => $data['reason'] ?? 'Transferred', 'created_by' => $actor->id]);

            $this->load($enrollment);
            $this->timeline->record($enrollment->patient, 'enrollment.transferred', "Transferred: {$enrollment->summary()}", $enrollment,
                description: $data['reason'] ?? null, branchId: $enrollment->branch_id, visibility: 'parent');

            return $enrollment;
        });
    }

    public function load(Enrollment $enrollment): Enrollment
    {
        return $enrollment->load([
            'patient', 'branch',
            'trainingEnrollment.trainingGroup', 'trainingEnrollment.trainer',
            'therapyEnrollment.service', 'therapyEnrollment.therapist',
            'assignments.trainer', 'assignments.trainingGroup', 'assignments.therapist',
        ]);
    }

    private function validateTraining(Patient $patient, array $data): array
    {
        $alreadyEnrolled = $patient->enrollments()->open()->where('type', EnrollmentType::Training)->exists();
        if ($alreadyEnrolled) {
            throw ValidationException::withMessages([
                'type' => 'This child already has an open Regular Training enrollment. Transfer it to another class instead.',
            ]);
        }

        $group = $this->trainingGroup($data['training_group_id'] ?? null, (int) $data['branch_id']);
        $trainerId = $data['trainer_id'] ?? $group->lead_trainer_id;
        $this->activeTrainer($trainerId);

        return [
            'training_group_id' => $group->id,
            'trainer_id' => $trainerId,
            'monthly_fee' => $data['monthly_fee'] ?? null,
        ];
    }

    private function validateTherapy(Patient $patient, array $data): array
    {
        $service = Service::find($data['service_id'] ?? null);
        if (! $service || $service->category !== ServiceCategory::Therapy || ! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => 'Choose an active therapy service.']);
        }

        $duplicate = $patient->enrollments()->open()
            ->where('type', EnrollmentType::Therapy)
            ->whereHas('therapyEnrollment', fn ($q) => $q->where('service_id', $service->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['service_id' => "This child is already enrolled in {$service->name}."]);
        }

        $therapist = $this->activeTherapist($data['therapist_id'] ?? null, $service->id);

        return [
            'service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'sessions_per_week' => $data['sessions_per_week'] ?? null,
            'session_duration_min' => $data['session_duration_min'] ?? $service->default_duration_min,
            'billing_mode' => $data['billing_mode'] ?? 'per_session',
        ];
    }

    private function trainingGroup(?int $groupId, int $branchId, bool $checkCapacity = true): TrainingGroup
    {
        $group = TrainingGroup::find($groupId);

        if (! $group || ! $group->isActive()) {
            throw ValidationException::withMessages(['training_group_id' => 'Choose an active class.']);
        }
        if ($group->branch_id !== $branchId) {
            throw ValidationException::withMessages(['training_group_id' => 'The class belongs to a different branch.']);
        }
        if ($checkCapacity && $group->max_students && $group->occupiedSeats() >= $group->max_students) {
            throw ValidationException::withMessages(['training_group_id' => "{$group->name} is full ({$group->max_students} students)."]);
        }

        return $group;
    }

    private function activeTrainer(?int $trainerId): Trainer
    {
        $trainer = Trainer::find($trainerId);
        if (! $trainer || ! $trainer->isActive()) {
            throw ValidationException::withMessages(['trainer_id' => 'Choose an active trainer (the class has no lead trainer).']);
        }

        return $trainer;
    }

    private function activeTherapist(?int $therapistId, int $serviceId): Therapist
    {
        $therapist = Therapist::find($therapistId);
        if (! $therapist || ! $therapist->isActive()) {
            throw ValidationException::withMessages(['therapist_id' => 'Choose an active therapist.']);
        }
        if (! $therapist->provides($serviceId)) {
            throw ValidationException::withMessages(['therapist_id' => "{$therapist->name} does not provide this therapy."]);
        }

        return $therapist;
    }
}
