<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['therapy_session_id', 'patient_id', 'date', 'status', 'comment', 'user_id'])]
class HomePracticeLog extends Model
{
    public const STATUSES = ['done', 'partly', 'not_done'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'therapy_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
