<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\BranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->accessibleBy($request->user())
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('code', 'like', '%'.$request->input('search').'%')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request): JsonResponse
    {
        Gate::authorize('create', Branch::class);

        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        Gate::authorize('view', $branch);

        return new BranchResource($branch->loadCount('users'));
    }

    public function update(BranchRequest $request, Branch $branch): BranchResource
    {
        Gate::authorize('update', $branch);

        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        Gate::authorize('delete', $branch);

        $branch->delete();

        return response()->json(null, 204);
    }
}
