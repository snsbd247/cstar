<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\TrainingRecord;
use App\Services\AmendmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Amendments to finalized clinical notes (Sprint 22). Only the note's author amends; everyone who may read it sees the history. */
class ClinicalAmendmentController extends Controller
{
    public function __construct(private AmendmentService $amendments) {}

    public function session(Request $request, Appointment $appointment): JsonResponse
    {
        $session = $appointment->session ?? abort(404);
        if ($request->isMethod('get')) {
            Gate::authorize('viewSession', $appointment);

            return $this->history($session);
        }
        Gate::authorize('writeSession', $appointment);

        return $this->store($request, $session);
    }

    public function assessment(Request $request, Assessment $assessment): JsonResponse
    {
        Gate::authorize($request->isMethod('get') ? 'view' : 'update', $assessment);

        return $request->isMethod('get') ? $this->history($assessment) : $this->store($request, $assessment);
    }

    public function trainingRecord(Request $request, TrainingRecord $trainingRecord): JsonResponse
    {
        if ($request->isMethod('get')) {
            Gate::authorize(Permission::TRAINING_RECORDS_VIEW);
            abort_unless(Patient::visibleTo($request->user())->whereKey($trainingRecord->patient_id)->exists(), 403);

            return $this->history($trainingRecord);
        }
        abort_unless($request->user()->can(Permission::TRAINING_RECORDS_WRITE) && $trainingRecord->trainer_id === $request->user()->trainer?->id, 403,
            'Only the trainer who wrote this record can amend it.');

        return $this->store($request, $trainingRecord);
    }

    private function store(Request $request, Model $record): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'max:60'],
            'value' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], ['reason.required' => 'Say why the note is being amended.']);
        $amendment = $this->amendments->amend($record, $data['field'], $data['value'] ?? null, $data['reason'], $request->user());

        return response()->json(['data' => $amendment->toRow(), 'history' => $this->amendments->history($record)], 201);
    }

    private function history(Model $record): JsonResponse
    {
        return response()->json(['data' => $this->amendments->history($record)]);
    }
}
