<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\GuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientService;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class GuardianController extends Controller
{
    public function __construct(private PatientService $patients, private TimelineService $timeline) {}

    /** Find an existing guardian by exact mobile number (siblings share guardians). */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize(Permission::GUARDIANS_MANAGE);
        $request->validate(['phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/']]);

        $guardians = Guardian::with('patients:id,patient_code,name')->where('phone', $request->input('phone'))->get();

        return response()->json(['data' => $guardians->map(fn (Guardian $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'phone' => $g->phone,
            'children' => $g->patients->map->only(['patient_code', 'name']),
        ])]);
    }

    public function store(GuardianRequest $request, Patient $patient): AnonymousResourceCollection
    {
        Gate::authorize('manageGuardians', $patient);

        if ($request->filled('id') && $patient->guardians()->whereKey($request->integer('id'))->exists()) {
            throw ValidationException::withMessages(['id' => 'This guardian is already linked to the child.']);
        }

        $guardian = $this->patients->attachGuardian($patient, $request->validated());
        $this->timeline->record($patient, 'guardian.added', "Guardian added: {$guardian->name}", $guardian);

        return GuardianResource::collection($patient->guardians()->get());
    }

    public function update(GuardianRequest $request, Patient $patient, Guardian $guardian): AnonymousResourceCollection
    {
        Gate::authorize('manageGuardians', $patient);
        abort_unless($patient->guardians()->whereKey($guardian->id)->exists(), 404);

        DB::transaction(function () use ($request, $patient, $guardian) {
            $guardian->update(Arr::only($request->validated(), ['name', 'phone', 'alt_phone', 'email', 'occupation', 'nid', 'address']));
            $this->patients->attachGuardian($patient, ['id' => $guardian->id, ...$request->validated()]);
        });

        return GuardianResource::collection($patient->guardians()->get());
    }

    public function destroy(Patient $patient, Guardian $guardian): AnonymousResourceCollection
    {
        Gate::authorize('manageGuardians', $patient);

        if ($patient->guardians()->count() <= 1) {
            throw ValidationException::withMessages(['guardian' => 'A child must keep at least one guardian.']);
        }

        $patient->guardians()->detach($guardian->id);

        return GuardianResource::collection($patient->guardians()->get());
    }

    /** Reception creates the parent-portal login (decision D5: mobile + password, changed at first login). */
    public function createPortalAccount(Request $request, Guardian $guardian): JsonResponse
    {
        Gate::authorize(Permission::GUARDIANS_MANAGE);
        $visible = Patient::visibleTo($request->user())->whereHas('guardians', fn ($g) => $g->whereKey($guardian->id))->exists();
        abort_unless($visible, 403);

        if ($guardian->user_id) {
            throw ValidationException::withMessages(['guardian' => 'This guardian already has a portal account.']);
        }

        $request->validate(['password' => ['required', 'string', Password::defaults()]]);
        if (User::where('phone', $guardian->phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Another account already uses this mobile number.']);
        }

        $user = DB::transaction(function () use ($request, $guardian) {
            $user = User::create([
                'name' => $guardian->name,
                'phone' => $guardian->phone,
                'email' => $guardian->email && ! User::where('email', $guardian->email)->exists() ? $guardian->email : null,
                'password' => $request->input('password'),
                'user_type' => UserType::Parent,
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ]);
            $user->assignRole(Role::Parent->value);
            $user->branches()->sync($guardian->patients()->pluck('home_branch_id')->unique());
            $guardian->update(['user_id' => $user->id]);

            return $user;
        });

        return response()->json(['message' => 'Portal account created.', 'data' => ['user_id' => $user->id, 'login' => $user->phone]], 201);
    }
}
