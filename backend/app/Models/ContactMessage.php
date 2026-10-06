<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'name', 'phone', 'email', 'subject', 'message', 'status', 'handled_by', 'ip_address'])]
class ContactMessage extends Model
{
    public const STATUSES = ['new', 'read', 'replied', 'spam'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Messages without a branch are general enquiries every front desk may answer. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleBranchIds();

        return $ids === null ? $query : $query->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $ids));
    }
}
