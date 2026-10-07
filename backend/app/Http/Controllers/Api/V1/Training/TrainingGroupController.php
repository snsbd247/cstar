<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingGroupResource;
use App\Models\TrainingGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Classes ("training groups"): roster = current training enrollments. */
class TrainingGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TrainingGroup::class);
        $user = $request->user();
        $trainerOnly = $user->hasRole(Role::Trainer->value) && ! $user->hasAnyRole([Role::SuperAdmin->value, Role::BranchAdmin->value, Role::Receptionist->value]);
        $branchIds = $user->accessibleBranchIds();

        $groups = TrainingGroup::with(['branch:id,name', 'leadTrainer:id,name', 'schedules'])
            ->withCount(['trainingEnrollments as students_count' => fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->whereIn('status', EnrollmentStatus::open()))])
            ->when($trainerOnly, fn ($q) => $q->taughtBy($user->trainer?->id ?? 0))
            ->when(! $trainerOnly && $branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')->get();

        return TrainingGroupResource::collection($groups);
    }

    public function show(TrainingGroup $class): TrainingGroupResource
    {
        Gate::authorize('view', $class);

        return new TrainingGroupResource($class->load(['branch:id,name', 'leadTrainer:id,name', 'schedules']));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TrainingGroup::class);
        $data = $this->validated($request);
        abort_unless($request->user()->canAccessBranch($data['branch_id']), 403);

        $group = DB::transaction(function () use ($data) {
            $group = TrainingGroup::create(collect($data)->except('schedules')->all());
            $group->schedules()->createMany($data['schedules'] ?? []);

            return $group;
        });

        return (new TrainingGroupResource($group->load(['branch', 'leadTrainer', 'schedules'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, TrainingGroup $class): TrainingGroupResource
    {
        Gate::authorize('update', $class);
        $data = $this->validated($request, $class);

        DB::transaction(function () use ($class, $data) {
            $class->update(collect($data)->except(['schedules', 'branch_id'])->all());
            if (array_key_exists('schedules', $data)) {
                $class->schedules()->delete();
                $class->schedules()->createMany($data['schedules']);
            }
        });

        return new TrainingGroupResource($class->load(['branch', 'leadTrainer', 'schedules']));
    }

    /** Current students of a class. */
    public function roster(TrainingGroup $class): JsonResponse
    {
        Gate::authorize('view', $class);

        $enrollments = $class->trainingEnrollments()->with(['enrollment.patient', 'trainer:id,name'])
            ->whereHas('enrollment', fn ($e) => $e->whereIn('status', EnrollmentStatus::open()))->get();

        return response()->json(['data' => $enrollments->map(fn ($t) => [
            'enrollment_id' => $t->enrollment_id,
            'status' => $t->enrollment->status,
            'start_date' => $t->enrollment->start_date->toDateString(),
            'trainer' => $t->trainer?->only(['id', 'name']),
            'patient' => $t->enrollment->patient->only(['id', 'name', 'patient_code']) + ['has_photo' => $t->enrollment->patient->photo_path !== null],
        ])->sortBy('patient.name')->values()]);
    }

    private function validated(Request $request, ?TrainingGroup $group = null): array
    {
        return $request->validate([
            'branch_id' => [$group ? 'prohibited' : 'required', 'integer', Rule::exists('branches', 'id')],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('training_groups')->ignore($group?->id)],
            'name' => ['required', 'string', 'max:255'],
            'lead_trainer_id' => ['nullable', 'integer', Rule::exists('trainers', 'id')->where('status', 'active')],
            'max_students' => ['nullable', 'integer', 'between:1,100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'schedules' => ['sometimes', 'array', 'max:7'],
            'schedules.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
        ]);
    }
}
