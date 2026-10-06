<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'name_bn', 'is_active', 'sort_order'])]
class Diagnosis extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
