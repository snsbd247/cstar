<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Individual plan with goals, owned by one enrollment: an ITP for a training enrollment,
 * a therapy plan for a therapy enrollment. One active plan per enrollment.
 */
#[Fillable(['enrollment_id', 'patient_id', 'title', 'start_date', 'review_date', 'status', 'notes', 'created_by'])]
class IndividualPlan extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'review_date' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PlanGoal::class)->orderBy('sort_order')->orderBy('id');
    }
}
