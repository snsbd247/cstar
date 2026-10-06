<?php

namespace Tests\Concerns;

use App\Enums\EnrollmentType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\EnrollmentService;

trait CreatesClinicData
{
    protected function trainerFor(Branch $branch, ?User $user = null): Trainer
    {
        return Trainer::factory()->create(['branch_id' => $branch->id, 'user_id' => $user?->id]);
    }

    protected function therapistFor(Service $service, ?User $user = null, ?Branch $branch = null): Therapist
    {
        $therapist = Therapist::factory()->create(['user_id' => $user?->id, 'primary_branch_id' => $branch?->id ?? Branch::factory()]);
        $therapist->services()->attach($service);

        return $therapist;
    }

    protected function classFor(Branch $branch, ?Trainer $lead = null, int $max = 10): TrainingGroup
    {
        return TrainingGroup::factory()->create([
            'branch_id' => $branch->id,
            'lead_trainer_id' => ($lead ?? $this->trainerFor($branch))->id,
            'max_students' => $max,
        ]);
    }

    protected function enrollTraining(Patient $patient, TrainingGroup $class, array $extra = []): Enrollment
    {
        return app(EnrollmentService::class)->create($patient, [
            'type' => EnrollmentType::Training->value,
            'branch_id' => $class->branch_id,
            'training_group_id' => $class->id,
            'start_date' => today()->toDateString(),
            ...$extra,
        ], $this->systemUser());
    }

    protected function enrollTherapy(Patient $patient, Service $service, Therapist $therapist, ?Branch $branch = null): Enrollment
    {
        return app(EnrollmentService::class)->create($patient, [
            'type' => EnrollmentType::Therapy->value,
            'branch_id' => ($branch ?? $patient->homeBranch)->id,
            'service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'start_date' => today()->toDateString(),
        ], $this->systemUser());
    }

    private function systemUser(): User
    {
        return User::role(Role::SuperAdmin->value)->first() ?? $this->userWithRole(Role::SuperAdmin);
    }
}
