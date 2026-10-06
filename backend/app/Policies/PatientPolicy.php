<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;

/**
 * Permission (what kind of action) AND visibility (which child) must both pass.
 * Visibility is defined once in Patient::scopeVisibleTo(). Super Admin passes via Gate::before.
 */
class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PATIENTS_VIEW);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can(Permission::PATIENTS_VIEW) && $this->visible($user, $patient);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PATIENTS_CREATE);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can(Permission::PATIENTS_UPDATE) && $this->visible($user, $patient);
    }

    /** Diagnosis, medical/developmental history, clinical documents. */
    public function viewClinical(User $user, Patient $patient): bool
    {
        return $user->can(Permission::PATIENTS_VIEW_CLINICAL) && $this->visible($user, $patient);
    }

    public function manageGuardians(User $user, Patient $patient): bool
    {
        return $user->can(Permission::GUARDIANS_MANAGE) && $this->visible($user, $patient);
    }

    public function enroll(User $user, Patient $patient): bool
    {
        return $user->can(Permission::ENROLLMENTS_MANAGE) && $this->visible($user, $patient);
    }

    public function viewDocument(User $user, Patient $patient, PatientDocument $document): bool
    {
        return $document->isClinical() ? $this->viewClinical($user, $patient) : $this->view($user, $patient);
    }

    private function visible(User $user, Patient $patient): bool
    {
        return Patient::visibleTo($user)->whereKey($patient->id)->exists();
    }
}
