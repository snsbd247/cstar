<?php

namespace App\Http\Resources;

use App\Models\PatientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PatientDocument */
class PatientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'visible_to_parent' => $this->visible_to_parent,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at' => $this->created_at,
        ];
    }
}
