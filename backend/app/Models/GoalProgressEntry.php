<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['plan_goal_id', 'source_type', 'source_id', 'date', 'score', 'note', 'recorded_by'])]
class GoalProgressEntry extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(PlanGoal::class, 'plan_goal_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
