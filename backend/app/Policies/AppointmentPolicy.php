<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

/** Super Admin passes via Gate::before. */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::APPOINTMENTS_VIEW);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::APPOINTMENTS_VIEW) && Patient::visibleTo($user)->whereKey($appointment->patient_id)->exists();
    }

    /** Booking for a branch: front desk / admins of that branch. */
    public function create(User $user, int $branchId): bool
    {
        return $user->can(Permission::APPOINTMENTS_MANAGE) && $user->canAccessBranch($branchId);
    }

    public function manage(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::APPOINTMENTS_MANAGE) && $user->canAccessBranch($appointment->branch_id);
    }

    /** The treating therapist may check the child in or mark a no-show on their own appointments. */
    public function attend(User $user, Appointment $appointment): bool
    {
        return $this->manage($user, $appointment) || $this->isTreatingTherapist($user, $appointment);
    }

    /**
     * Clinical note: only the therapist who saw the child writes it (not office staff, not trainers).
     * THERAPY SESSION ≠ TRAINING SESSION.
     */
    public function writeSession(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::THERAPY_SESSIONS_WRITE) && $this->isTreatingTherapist($user, $appointment);
    }

    public function viewSession(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::THERAPY_SESSIONS_VIEW) && Patient::visibleTo($user)->whereKey($appointment->patient_id)->exists();
    }

    private function isTreatingTherapist(User $user, Appointment $appointment): bool
    {
        $therapistId = $user->therapist?->id;

        return $therapistId !== null && $appointment->therapist_id === $therapistId;
    }
}
