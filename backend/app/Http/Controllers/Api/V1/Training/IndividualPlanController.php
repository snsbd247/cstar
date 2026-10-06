<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndividualPlanResource;
use App\Models\Enrollment;
use App\Models\IndividualPlan;
use App\Models\PlanGoal;
use App\Services\IndividualPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Individual plans with goals (ITP for training; therapy plans use the same endpoints). */
class IndividualPlanController extends Controller
{
    public function __construct(private IndividualPlanService $plans) {}

    public function index(Enrollment $enrollment): AnonymousResourceCollection
    {
        Gate::authorize('viewPlans', $enrollment);

        return IndividualPlanResource::collection($enrollment->plans()->with('goals.progressEntries')->get());
    }

    public function store(Request $request, Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('writePlans', $enrollment);
        $plan = $this->plans->create($enrollment, $this->validated($request, true), $request->user());

        return (new IndividualPlanResource($plan))->response()->setStatusCode(201);
    }

    public function update(Request $request, IndividualPlan $plan): IndividualPlanResource
    {
        Gate::authorize('writePlans', $plan->enrollment);

        return new IndividualPlanResource($this->plans->update($plan, $this->validated($request, false))->load('goals.progressEntries'));
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => [$creating ? 'prohibited' : 'sometimes', Rule::in(['active', 'closed'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'goals' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:20'],
            'goals.*.id' => [$creating ? 'prohibited' : 'nullable', 'integer'],
            'goals.*.domain' => ['nullable', 'string', 'max:50'],
            'goals.*.title' => ['required', 'string', 'max:255'],
            'goals.*.target' => ['nullable', 'string', 'max:2000'],
            'goals.*.baseline_level' => ['nullable', 'string', 'max:2000'],
            'goals.*.current_level' => ['nullable', 'string', 'max:2000'],
            'goals.*.activities' => ['nullable', 'string', 'max:2000'],
            'goals.*.measurement' => ['nullable', 'string', 'max:255'],
            'goals.*.progress_percent' => ['nullable', 'integer', 'between:0,100'],
            'goals.*.review_date' => ['nullable', 'date'],
            'goals.*.status' => ['nullable', Rule::in(PlanGoal::STATUSES)],
        ], ['goals.required' => 'Add at least one goal.']);
    }
}
