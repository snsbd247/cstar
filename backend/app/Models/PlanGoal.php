<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'individual_plan_id', 'domain', 'title', 'target', 'baseline_level', 'current_level', 'activities',
    'measurement', 'progress_percent', 'review_date', 'status', 'sort_order',
])]
class PlanGoal extends Model
{
    use Auditable;

    public const STATUSES = ['not_started', 'in_progress', 'achieved', 'discontinued'];

    protected function casts(): array
    {
        return ['review_date' => 'date'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(IndividualPlan::class, 'individual_plan_id');
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(GoalProgressEntry::class)->latest('date')->latest('id');
    }
}
