<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Trainer staff records (TRAINER ≠ THERAPIST — therapists are managed separately). */
class TrainerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::TRAINERS_VIEW);
        $branchIds = $request->user()->accessibleBranchIds();

        $trainers = Trainer::with(['branch:id,name', 'user:id,email,phone'])
            ->withCount(['ledGroups as classes_count' => fn ($q) => $q->where('status', 'active')])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')->get();

        return response()->json(['data' => $trainers->map(fn (Trainer $t) => [
            ...$t->only(['id', 'name', 'phone', 'email', 'qualification', 'experience_years', 'status', 'employee_code', 'classes_count', 'user_id']),
            'branch' => $t->branch?->only(['id', 'name']),
            'login' => $t->user ? ($t->user->email ?? $t->user->phone) : null,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::TRAINERS_MANAGE);
        $data = $this->validated($request);
        $trainer = Trainer::create([...$data, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);

        return response()->json(['data' => ['id' => $trainer->id]], 201);
    }

    public function update(Request $request, Trainer $trainer): JsonResponse
    {
        Gate::authorize(Permission::TRAINERS_MANAGE);
        abort_unless($request->user()->canAccessBranch($trainer->branch_id), 403);

        $trainer->update($this->validated($request, $trainer));

        return response()->json(['message' => 'Trainer updated.']);
    }

    private function validated(Request $request, ?Trainer $trainer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('trainers')->ignore($trainer?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'between:0,60'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable', 'integer', Rule::unique('trainers')->ignore($trainer?->id)],
        ]);

        abort_unless($request->user()->canAccessBranch($data['branch_id']), 403);

        // The linked login must be a Trainer account (TRAINER ≠ THERAPIST).
        if (! empty($data['user_id']) && ! User::whereKey($data['user_id'])->role(Role::Trainer->value)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Choose a user account with the Trainer role.']);
        }

        return $data;
    }
}
