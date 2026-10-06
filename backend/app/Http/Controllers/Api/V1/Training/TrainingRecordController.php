<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingRecordResource;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingRecord;
use App\Services\TrainingRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** Daily training records (TRAINING SESSION ≠ THERAPY SESSION). */
class TrainingRecordController extends Controller
{
    public function __construct(private TrainingRecordService $records) {}

    /** GET /classes/{class}/records?date= — who attended and who still needs a record. */
    public function forDay(Request $request, TrainingGroup $class): JsonResponse
    {
        Gate::authorize('writeRecords', $class);
        $date = Carbon::parse($request->input('date', today()));

        $attended = TrainingAttendance::with('patient:id,name,patient_code,photo_path')
            ->where('training_group_id', $class->id)->whereDate('date', $date)
            ->whereIn('status', ['present', 'late'])->get();
        $records = TrainingRecord::with(['activities', 'goalScores'])
            ->whereHas('session', fn ($s) => $s->where('training_group_id', $class->id)->whereDate('date', $date))
            ->get()->keyBy('enrollment_id');

        return response()->json(['data' => $attended->map(fn ($a) => [
            'enrollment_id' => $a->enrollment_id,
            'attendance' => $a->status,
            'patient' => ['id' => $a->patient->id, 'name' => $a->patient->name, 'patient_code' => $a->patient->patient_code, 'has_photo' => $a->patient->photo_path !== null],
            'record' => $records->has($a->enrollment_id) ? new TrainingRecordResource($records[$a->enrollment_id]) : null,
        ])->sortBy('patient.name')->values()]);
    }

    /** POST /classes/{class}/records — create or update the day's record for one student. */
    public function store(Request $request, TrainingGroup $class): JsonResponse
    {
        Gate::authorize('writeRecords', $class);

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'activity_ids' => ['array'],
            'activity_ids.*' => ['integer', 'exists:activity_types,id'],
            'goals_worked' => ['nullable', 'string', 'max:2000'],
            'observation' => ['nullable', 'string', 'max:5000'],
            'performance' => ['nullable', 'integer', 'between:1,5'],
            'progress' => ['nullable', 'string', 'max:5000'],
            'challenges' => ['nullable', 'string', 'max:5000'],
            'trainer_notes' => ['nullable', 'string', 'max:5000'],
            'parent_note' => ['nullable', 'string', 'max:2000'],
            'next_plan' => ['nullable', 'string', 'max:2000'],
            'goal_scores' => ['array'],
            'goal_scores.*.goal_id' => ['required', 'integer'],
            'goal_scores.*.score' => ['required', 'integer', 'between:1,5'],
            'goal_scores.*.note' => ['nullable', 'string', 'max:500'],
            'finalize' => ['boolean'],
        ]);

        $record = $this->records->save($class, Carbon::parse($data['date']), Enrollment::findOrFail($data['enrollment_id']), $data, $request->user());

        return (new TrainingRecordResource($record))->response()->setStatusCode($record->wasRecentlyCreated ? 201 : 200);
    }

    /** GET /training-records?patient_id=&class_id=&from=&to= — history for children the user may see. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::TRAINING_RECORDS_VIEW);

        $records = TrainingRecord::with(['patient:id,name,patient_code', 'trainer:id,name', 'session.trainingGroup:id,name', 'activities'])
            ->whereHas('patient', fn ($p) => $p->visibleTo($request->user()))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('session', fn ($s) => $s->where('training_group_id', $request->integer('class_id'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return TrainingRecordResource::collection($records);
    }

    public function show(Request $request, TrainingRecord $trainingRecord): TrainingRecordResource
    {
        Gate::authorize(Permission::TRAINING_RECORDS_VIEW);
        abort_unless(Patient::visibleTo($request->user())->whereKey($trainingRecord->patient_id)->exists(), 403);

        return new TrainingRecordResource($trainingRecord->load(['patient', 'trainer', 'session.trainingGroup', 'activities', 'goalScores']));
    }
}
