<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Speech & Language, Developmental, Autism-related … each with its own findings sections. */
#[Fillable(['name', 'name_bn', 'sections', 'is_active', 'sort_order'])]
class AssessmentType extends Model
{
    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
