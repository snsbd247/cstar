<?php

namespace App\Http\Resources;

use App\Models\IndividualPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IndividualPlan */
class IndividualPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'title' => $this->title,
            'start_date' => $this->start_date->toDateString(),
            'review_date' => $this->review_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'goals' => $this->whenLoaded('goals', fn () => $this->goals->map(fn ($g) => [
                ...$g->only(['id', 'domain', 'title', 'target', 'baseline_level', 'current_level', 'activities', 'measurement', 'progress_percent', 'status', 'sort_order']),
                'review_date' => $g->review_date?->toDateString(),
                'recent_scores' => $g->relationLoaded('progressEntries')
                    ? $g->progressEntries->take(10)->map(fn ($e) => ['date' => $e->date->toDateString(), 'score' => $e->score, 'note' => $e->note])->values()
                    : [],
            ])),
        ];
    }
}
