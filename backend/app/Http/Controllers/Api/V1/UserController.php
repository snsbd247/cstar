<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $branchIds = $request->user()->accessibleBranchIds();
        $search = $request->input('search');

        $users = User::query()
            ->with(['roles', 'branches'])
            ->when($branchIds !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds)))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($request->filled('role'), fn ($q) => $q->role($request->input('role')))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $request->integer('branch_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = DB::transaction(fn () => $this->save(new User, $request));

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        return new UserResource(DB::transaction(fn () => $this->save($user, $request)));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return response()->json(null, 204);
    }

    private function save(User $user, UserRequest $request): User
    {
        $data = $request->safe()->except(['role', 'branch_ids', 'password']);
        $role = Role::from($request->input('role'));

        $user->fill($data);
        $user->user_type = $role === Role::Parent ? UserType::Parent : UserType::Staff;

        if ($request->filled('password')) {
            $user->password = $request->input('password');
        }

        $user->save();
        $user->syncRoles([$role->value]);

        $branchIds = $request->input('branch_ids');
        $user->branches()->sync(collect($branchIds)->mapWithKeys(
            fn ($id, $i) => [$id => ['is_primary' => $i === 0]],
        ));

        return $user->load(['roles', 'branches']);
    }
}
