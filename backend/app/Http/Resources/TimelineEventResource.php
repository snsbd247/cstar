<?php

namespace App\Http\Resources;

use App\Models\TimelineEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimelineEvent */
class TimelineEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'occurred_at' => $this->occurred_at,
        ];
    }
}
