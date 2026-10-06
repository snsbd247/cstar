<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\IndividualPlan;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Individual Training Plan (Plan §১৩) — and later therapy plans — with goals. One active plan per enrollment. */
class IndividualPlanService
{
    public const GOAL_FIELDS = ['domain', 'title', 'target', 'baseline_level', 'current_level', 'activities', 'measurement', 'progress_percent', 'review_date', 'status', 'sort_order'];

    public function __construct(private TimelineService $timeline) {}

    public function create(Enrollment $enrollment, array $data, User $user): IndividualPlan
    {
        if ($enrollment->plans()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['plan' => 'This enrollment already has an active plan. Close it before starting a new one.']);
        }

        return DB::transaction(function () use ($enrollment, $data, $user) {
            $plan = $enrollment->plans()->create([
                ...Arr::only($data, ['title', 'start_date', 'review_date', 'notes']),
                'patient_id' => $enrollment->patient_id,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            foreach ($data['goals'] ?? [] as $i => $goal) {
                $plan->goals()->create([...Arr::only($goal, self::GOAL_FIELDS), 'sort_order' => $goal['sort_order'] ?? $i]);
            }

            $this->timeline->record($enrollment->patient, 'plan.created', "New plan: {$plan->title}", $plan,
                description: count($data['goals'] ?? []).' goals', branchId: $enrollment->branch_id, visibility: 'parent');

            return $plan->load('goals');
        });
    }

    public function update(IndividualPlan $plan, array $data): IndividualPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update(Arr::only($data, ['title', 'start_date', 'review_date', 'notes', 'status']));

            if (array_key_exists('goals', $data)) {
                $keep = [];
                foreach ($data['goals'] as $i => $goal) {
                    $values = [...Arr::only($goal, self::GOAL_FIELDS), 'sort_order' => $goal['sort_order'] ?? $i];
                    $model = isset($goal['id'])
                        ? tap($plan->goals()->findOrFail($goal['id']))->update($values)
                        : $plan->goals()->create($values);
                    $keep[] = $model->id;
                }
                // Goals with recorded progress are discontinued rather than deleted, to keep history.
                $plan->goals()->whereNotIn('id', $keep)->get()->each(fn ($g) => $g->progressEntries()->exists()
                    ? $g->update(['status' => 'discontinued'])
                    : $g->delete());
            }

            return $plan->load('goals');
        });
    }
}
