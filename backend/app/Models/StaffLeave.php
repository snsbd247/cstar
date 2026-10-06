<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'type', 'start_date', 'end_date', 'days', 'reason', 'therapist_leave_id', 'created_by'])]
class StaffLeave extends Model
{
    use Auditable;

    public const TYPES = ['casual' => 'Casual', 'sick' => 'Sick', 'annual' => 'Annual', 'unpaid' => 'Unpaid', 'other' => 'Other'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
