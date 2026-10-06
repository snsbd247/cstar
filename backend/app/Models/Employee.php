<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** Every paid staff member (A4). A trainer or therapist profile may point here for pay. */
#[Fillable([
    'employee_code', 'name', 'designation', 'department', 'branch_id', 'joining_date', 'left_date', 'employment_type',
    'pay_type', 'phone', 'payment_method', 'bank_name', 'bank_account', 'mfs_number', 'user_id', 'status', 'notes',
])]
class Employee extends Model
{
    use Auditable;

    public const DEPARTMENTS = ['therapist', 'trainer', 'admin', 'support'];

    public const PAY_TYPES = ['fixed', 'per_session', 'mixed', 'revenue_share'];

    protected function casts(): array
    {
        return ['joining_date' => 'date', 'left_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapist(): HasOne
    {
        return $this->hasOne(Therapist::class);
    }

    public function trainer(): HasOne
    {
        return $this->hasOne(Trainer::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class)->orderByDesc('effective_from');
    }

    public function sessionRates(): HasMany
    {
        return $this->hasMany(SessionPayRate::class)->orderByDesc('effective_from');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    /** The salary structure in force on a date. */
    public function structureOn(Carbon $date): ?SalaryStructure
    {
        return $this->salaryStructures()->whereDate('effective_from', '<=', $date)->first();
    }

    public function earnsSessions(): bool
    {
        return in_array($this->pay_type, ['per_session', 'mixed', 'revenue_share'], true);
    }
}
