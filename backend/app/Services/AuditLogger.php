<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'patient_id' => self::patientOf($subject),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /** The child a record belongs to, so Patient Activity can list everything about one child. */
    private static function patientOf(?Model $subject): ?int
    {
        if ($subject instanceof Patient) {
            return $subject->exists ? $subject->getKey() : null;
        }
        // Read raw attributes: strict mode throws on a model that has no patient_id column.
        $id = $subject?->getAttributes()['patient_id'] ?? null;

        return $id ? (int) $id : null;
    }
}
