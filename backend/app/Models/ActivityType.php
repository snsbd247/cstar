<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'name_bn', 'applies_to', 'is_active', 'sort_order'])]
class ActivityType extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
