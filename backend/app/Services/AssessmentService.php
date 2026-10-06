<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assessments (Plan §১৯, journey: Registration → Assessment → Enrollment).
 * Written by the assessing therapist; final assessments are locked and may be shared with parents.
 */
class AssessmentService
{
    public const TEXT_FIELDS = ['chief_complaint', 'background', 'summary', 'recommendations', 'parent_summary'];

    public function __construct(private IdGenerator $ids, private TimelineService $timeline) {}

    public function save(Patient $patient, array $data, User $user, ?Assessment $assessment = null): Assessment
    {
        if ($assessment?->isFinal()) {
            throw ValidationException::withMessages(['assessment' => 'This assessment is final and can no longer be changed.']);
        }

        if (! $assessment && ! $user->therapist) {
            throw ValidationException::withMessages(['assessment' => 'Only a therapist can write an assessment.']);
        }

        $type = AssessmentType::findOrFail($data['assessment_type_id'] ?? $assessment?->assessment_type_id);
        $appointment = isset($data['appointment_id']) ? Appointment::find($data['appointment_id']) : $assessment?->appointment;
        if ($appointment && $appointment->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['appointment_id' => 'This appointment belongs to another child.']);
        }

        $finalize = ! empty($data['finalize']);
        if ($finalize && blank($data['summary'] ?? $assessment?->summary)) {
            throw ValidationException::withMessages(['summary' => 'Write the overall summary before finalizing.']);
        }

        return DB::transaction(function () use ($patient, $data, $user, $assessment, $type, $appointment, $finalize) {
            $sectionKeys = collect($type->sections)->pluck('key')->all();
            $values = [
                ...Arr::only($data, self::TEXT_FIELDS),
                'assessment_type_id' => $type->id,
                'appointment_id' => $appointment?->id,
                'date' => $data['date'] ?? $assessment?->date ?? $appointment?->date ?? today(),
                'section_findings' => array_intersect_key($data['section_findings'] ?? $assessment?->section_findings ?? [], array_flip($sectionKeys)),
                'status' => $finalize ? 'final' : 'draft',
                'finalized_at' => $finalize ? now() : null,
            ];

            if ($assessment) {
                $assessment->update($values);
            } else {
                $assessment = Assessment::create([
                    ...$values,
                    'assessment_code' => $this->ids->next('assessment', 'ASM'),
                    'patient_id' => $patient->id,
                    'therapist_id' => $user->therapist->id,
                    'branch_id' => $appointment?->branch_id ?? $patient->home_branch_id,
                    'created_by' => $user->id,
                ]);
            }

            if (array_key_exists('recommendation_items', $data)) {
                $assessment->recommendationItems()->whereNull('enrollment_id')->delete();
                foreach ($data['recommendation_items'] as $item) {
                    $assessment->recommendationItems()->create(Arr::only($item, ['enrollment_type', 'service_id', 'frequency', 'priority', 'note']));
                }
            }

            if ($finalize) {
                if ($appointment && in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn], true)) {
                    $appointment->update(['status' => AppointmentStatus::Completed]);
                }
                if ($appointment) {
                    app(ChargeService::class)->assessmentCompleted($appointment, $user);
                }
                $this->timeline->record($patient, 'assessment.finalized', "Assessment: {$type->name}", $assessment,
                    description: $assessment->summary, branchId: $assessment->branch_id);
            }

            return $assessment->load(['type', 'therapist', 'recommendationItems.service', 'recommendationItems.enrollment']);
        });
    }

    /** Share (or un-share) a final assessment with the family in the parent portal. */
    public function share(Assessment $assessment, bool $shared): Assessment
    {
        if (! $assessment->isFinal()) {
            throw ValidationException::withMessages(['assessment' => 'Only a final assessment can be shared.']);
        }

        $assessment->update(['shared_with_parent' => $shared]);
        if ($shared) {
            $this->timeline->record($assessment->patient, 'assessment.shared', "Assessment report shared: {$assessment->type->name}", $assessment,
                description: $assessment->parent_summary, branchId: $assessment->branch_id, visibility: 'parent');
            app(NotificationService::class)->parentsTemplate($assessment->patient, 'assessment.shared',
                ['report' => $assessment->type->name_bn ?: $assessment->type->name], '/portal/progress');
        }

        return $assessment;
    }
}
