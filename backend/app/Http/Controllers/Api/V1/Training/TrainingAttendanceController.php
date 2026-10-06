<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\TrainingGroup;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Student attendance (STUDENT ATTENDANCE ≠ THERAPY APPOINTMENT). */
class TrainingAttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** GET /classes/{class}/attendance?date=YYYY-MM-DD */
    public function roster(Request $request, TrainingGroup $class): JsonResponse
    {
        Gate::authorize('viewAttendance', $class);
        $request->validate(['date' => ['nullable', 'date']]);

        return response()->json(['data' => $this->attendance->roster($class->load('schedules'), Carbon::parse($request->input('date', today())))]);
    }

    /** POST /classes/{class}/attendance — bulk mark the whole class in one tap. */
    public function mark(Request $request, TrainingGroup $class): JsonResponse
    {
        Gate::authorize('markAttendance', $class);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->attendance->mark($class, Carbon::parse($data['date']), $data['entries'], $request->user());

        return response()->json(['message' => "Attendance saved for $count students."]);
    }

    /** GET /classes/{class}/attendance/month?month=YYYY-MM */
    public function classMonth(Request $request, TrainingGroup $class): JsonResponse
    {
        Gate::authorize('viewAttendance', $class);
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => $this->attendance->classMonth($class, Carbon::parse(($request->input('month') ?? now()->format('Y-m')).'-01'))]);
    }

    /** GET /enrollments/{enrollment}/attendance?month=YYYY-MM — one student's month (Plan §১২). */
    public function studentMonth(Request $request, Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('viewAttendance', $enrollment);
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => $this->attendance->monthly($enrollment, Carbon::parse(($request->input('month') ?? now()->format('Y-m')).'-01'))]);
    }
}
