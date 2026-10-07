<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClinicalAmendment;
use App\Models\TherapySession;
use App\Models\TimelineEvent;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Amending finalized clinical records (Sprint 22 — Plan §২১ "শুধু amendment, কারণ সহ"). The record shows the
 * corrected text; the old text, reason, author and time stay in clinical_amendments for good. When the family-facing
 * summary is corrected, the parent's timeline entry is corrected too.
 */
class AmendmentService
{
    /** Fields that may be amended, per record type. Assessments also allow "section_findings.<key>". */
    public static function fields(Model $record): array
    {
        return match (true) {
            $record instanceof TherapySession => TherapySession::TEXT_FIELDS,
            $record instanceof Assessment => ['chief_complaint', 'background', 'summary', 'recommendations', 'parent_summary'],
            $record instanceof TrainingRecord => ['goals_worked', 'observation', 'progress', 'challenges', 'trainer_notes', 'parent_note', 'next_plan'],
        };
    }

    /** The field the family sees in their timeline. */
    private const PARENT_FIELD = [TherapySession::class => 'parent_summary', Assessment::class => 'parent_summary', TrainingRecord::class => 'parent_note'];

    public function amend(Model $record, string $field, ?string $value, string $reason, User $user): ClinicalAmendment
    {
        if (! $record->isFinal()) {
            throw ValidationException::withMessages(['field' => 'This note is still a draft — edit it directly.']);
        }
        $section = str_starts_with($field, 'section_findings.') ? substr($field, 17) : null;
        if (! in_array($field, self::fields($record), true) && ! ($record instanceof Assessment && $section !== null && $section !== '')) {
            throw ValidationException::withMessages(['field' => 'This part of the note cannot be amended.']);
        }
        $old = $section !== null ? ($record->section_findings[$section] ?? null) : $record->{$field};
        if ((string) $old === (string) $value) {
            throw ValidationException::withMessages(['value' => 'The new text is the same as the current text.']);
        }
        if ($field === 'parent_summary' && blank($value)) {
            throw ValidationException::withMessages(['value' => 'The summary for the family cannot be empty.']);
        }

        return DB::transaction(function () use ($record, $field, $section, $old, $value, $reason, $user) {
            if ($section !== null) {
                $record->update(['section_findings' => [...($record->section_findings ?? []), $section => $value]]);
            } else {
                $record->update([$field => $value]);
            }
            if ($field === (self::PARENT_FIELD[$record::class] ?? null)) {
                TimelineEvent::where('subject_type', $record->getMorphClass())->where('subject_id', $record->getKey())
                    ->where('visibility', 'parent')->update(['description' => $value]);
            }
            $amendment = ClinicalAmendment::create([
                'amendable_type' => $record->getMorphClass(), 'amendable_id' => $record->getKey(), 'field' => $field,
                'old_value' => $old, 'new_value' => $value, 'reason' => $reason, 'amended_by' => $user->id,
            ]);
            AuditLogger::log('clinical.amended', $record, ['field' => $field], ['field' => $field, 'reason' => $reason]);

            return $amendment->setRelation('author', $user);
        });
    }

    public function history(Model $record): array
    {
        return ClinicalAmendment::with('author:id,name')->where('amendable_type', $record->getMorphClass())->where('amendable_id', $record->getKey())
            ->latest('id')->get()->map->toRow()->all();
    }
}
