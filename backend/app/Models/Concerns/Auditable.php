<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes created / updated / deleted events of the model to audit_logs.
 * A model may define auditExclude(): array to keep fields (passwords, noise) out of the log.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changed = $model->auditValues($model->getChanges());
            unset($changed['updated_at']);

            if ($changed === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changed);
            AuditLogger::log('updated', $model, $model->auditValues($old), $changed);
        });

        static::deleted(function (Model $model) {
            AuditLogger::log('deleted', $model, $model->auditValues($model->getAttributes()), null);
        });
    }

    protected function auditValues(array $values): array
    {
        $exclude = method_exists($this, 'auditExclude') ? $this->auditExclude() : [];

        return array_diff_key($values, array_flip($exclude));
    }
}
