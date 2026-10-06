<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Writes the unified patient timeline (Plan §২০). */
class TimelineService
{
    public function record(
        Patient $patient,
        string $eventType,
        string $title,
        ?Model $subject = null,
        ?string $description = null,
        array $meta = [],
        string $visibility = 'internal',
        ?int $branchId = null,
    ): TimelineEvent {
        return $patient->timelineEvents()->create([
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'branch_id' => $branchId ?? $patient->home_branch_id,
            'actor_id' => Auth::id(),
            'visibility' => $visibility,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);
    }
}
