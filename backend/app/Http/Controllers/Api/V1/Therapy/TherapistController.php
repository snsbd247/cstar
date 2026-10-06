<?php

namespace App\Http\Controllers\Api\V1\Therapy;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\ServiceCategory;
use App\Enums\TherapistType;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use App\Models\TrainingGroupSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Therapist staff, the services they provide, weekly schedule and leave (TRAINER ≠ THERAPIST). */
class TherapistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_VIEW);

        $therapists = Therapist::with(['primaryBranch:id,name', 'services:id,name', 'user:id,email,phone', 'schedules.branch:id,name',
            'leaves' => fn ($q) => $q->whereDate('end_date', '>=', today())])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')->get();

        return response()->json(['data' => $therapists->map(fn (Therapist $t) => [
            ...$t->only(['id', 'name', 'designation', 'phone', 'email', 'qualification', 'experience_years', 'status', 'employee_code', 'user_id']),
            'therapist_type' => $t->therapist_type->value,
            'type_label' => $t->therapist_type->label(),
            'primary_branch' => $t->primaryBranch?->only(['id', 'name']),
            'services' => $t->services->map->only(['id', 'name']),
            'login' => $t->user ? ($t->user->email ?? $t->user->phone) : null,
            'schedules' => $t->schedules->map(fn ($s) => [
                'branch_id' => $s->branch_id, 'branch' => $s->branch?->name, 'weekday' => $s->weekday,
                'day' => TrainingGroupSchedule::DAYS[$s->weekday], 'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5), 'slot_minutes' => $s->slot_minutes,
            ]),
            'upcoming_leaves' => $t->leaves->map(fn ($l) => ['id' => $l->id, 'start_date' => $l->start_date->toDateString(), 'end_date' => $l->end_date->toDateString(), 'reason' => $l->reason]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_MANAGE);
        $data = $this->validated($request);

        $therapist = DB::transaction(function () use ($data) {
            $therapist = Therapist::create([...collect($data)->except('service_ids')->all(), 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
            $therapist->services()->sync($data['service_ids']);

            return $therapist;
        });

        return response()->json(['data' => ['id' => $therapist->id]], 201);
    }

    public function update(Request $request, Therapist $therapist): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_MANAGE);
        $data = $this->validated($request, $therapist);

        DB::transaction(function () use ($therapist, $data) {
            $therapist->update(collect($data)->except('service_ids')->all());
            $therapist->services()->sync($data['service_ids']);
        });

        return response()->json(['message' => 'Therapist updated.']);
    }

    /** PUT /therapists/{id}/schedule — replaces the weekly working hours. */
    public function updateSchedule(Request $request, Therapist $therapist): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_MANAGE);
        $data = $request->validate([
            'schedules' => ['present', 'array', 'max:21'],
            'schedules.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'schedules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.slot_minutes' => ['required', 'integer', 'between:15,180'],
        ]);
        foreach ($data['schedules'] as $s) {
            abort_unless($request->user()->canAccessBranch($s['branch_id']), 403);
        }

        DB::transaction(function () use ($therapist, $data) {
            $therapist->schedules()->delete();
            $therapist->schedules()->createMany($data['schedules']);
        });

        return response()->json(['message' => 'Schedule saved.']);
    }

    public function storeLeave(Request $request, Therapist $therapist): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_MANAGE);
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $leave = $therapist->leaves()->create([...$data, 'created_by' => $request->user()->id]);
        // Booked appointments inside the leave must be moved by the front desk.
        $affected = Appointment::where('therapist_id', $therapist->id)
            ->whereBetween('date', [$data['start_date'], $data['end_date']])->whereIn('status', ['pending', 'confirmed'])->count();

        return response()->json(['data' => ['id' => $leave->id, 'appointments_to_reschedule' => $affected]], 201);
    }

    public function destroyLeave(TherapistLeave $leave): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_MANAGE);
        $leave->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Therapist $therapist = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'therapist_type' => ['required', Rule::enum(TherapistType::class)],
            'primary_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('therapists')->ignore($therapist?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'between:0,60'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable', 'integer', Rule::unique('therapists')->ignore($therapist?->id)],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct'],
        ], ['service_ids.required' => 'Choose at least one service this therapist provides.']);

        abort_unless($request->user()->canAccessBranch($data['primary_branch_id']), 403);

        $therapyServices = Service::whereIn('id', $data['service_ids'])->where('category', '!=', ServiceCategory::Training)->count();
        if ($therapyServices !== count($data['service_ids'])) {
            throw ValidationException::withMessages(['service_ids' => 'Therapists provide therapy, assessment or consultation services — not training programs.']);
        }

        // The linked login must be a Therapist account (TRAINER ≠ THERAPIST).
        if (! empty($data['user_id']) && ! User::whereKey($data['user_id'])->role(Role::Therapist->value)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Choose a user account with the Therapist role.']);
        }

        return $data;
    }
}
