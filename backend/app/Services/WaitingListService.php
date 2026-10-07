<?php

namespace App\Services;

use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Models\Enrollment;
use App\Models\TrainingGroup;
use App\Models\WaitingListEntry;

/**
 * Waiting list (Sprint 20). When a seat in a class or a therapist's slot frees up, the front desk is told who
 * is waiting first; when a waiting child is enrolled, the entry closes by itself.
 */
class WaitingListService
{
    /** Called when an enrollment ends or leaves a class (transfer). */
    public function placeFreed(Enrollment $enrollment, ?int $leftGroupId = null): void
    {
        $type = $enrollment->type;
        $query = WaitingListEntry::with('patient:id,name')->where('status', 'waiting')->where('branch_id', $enrollment->branch_id)->where('type', $type->value);

        if ($type === EnrollmentType::Training) {
            $groupId = $leftGroupId ?? $enrollment->trainingEnrollment?->training_group_id;
            $group = $groupId ? TrainingGroup::find($groupId) : null;
            $query->where(fn ($q) => $q->whereNull('training_group_id')->orWhere('training_group_id', $groupId));
            $place = $group ? "a seat in {$group->name}" : 'a training seat';
        } else {
            $serviceId = $enrollment->therapyEnrollment?->service_id;
            $query->where('service_id', $serviceId);
            $place = 'a '.($enrollment->therapyEnrollment?->service?->name ?? 'therapy').' slot with '.($enrollment->therapyEnrollment?->therapist?->name ?? 'a therapist');
        }

        $waiting = $query->orderByRaw("priority = 'high' DESC")->orderBy('created_at')->get();
        if ($waiting->isEmpty()) {
            return;
        }
        app(NotificationService::class)->toStaff(Permission::ENROLLMENTS_MANAGE, $enrollment->branch_id, 'waiting_list.place_free',
            "Waiting list: {$place} is free", $waiting->count().' waiting — first: '.$waiting->first()->patient->name, '/app/enrollments/waiting-list');
    }

    /** Enrolling a waiting child closes their matching entry. */
    public function enrolled(Enrollment $enrollment): void
    {
        WaitingListEntry::where('patient_id', $enrollment->patient_id)->whereIn('status', ['waiting', 'offered'])->where('type', $enrollment->type->value)
            ->when($enrollment->type === EnrollmentType::Therapy, fn ($q) => $q->where(fn ($w) => $w->whereNull('service_id')->orWhere('service_id', $enrollment->therapyEnrollment?->service_id)))
            ->get()->each->update(['status' => 'enrolled', 'resolved_at' => now(), 'resolution' => "Enrolled ({$enrollment->enrollment_code})"]);
    }
}
