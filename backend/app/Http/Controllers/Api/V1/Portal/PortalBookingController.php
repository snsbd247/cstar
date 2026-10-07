<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Sprint 20 — parents of children in therapy book an extra / make-up session with their own therapist in a
 * free slot (pending until the front desk confirms), and cancel upcoming appointments themselves.
 */
class PortalBookingController extends Controller
{
    public function __construct(private SystemSettings $settings) {}

    private function child(Request $request, Patient $patient): void
    {
        $guardian = $request->user()->guardian;
        abort_unless($request->user()->can(Permission::PORTAL_ACCESS) && $guardian
            && $guardian->patients()->wherePivot('can_access_portal', true)->whereKey($patient->id)->exists(), 404);
    }

    private function enrollments(Patient $patient)
    {
        return Enrollment::with(['therapyEnrollment.service:id,name,name_bn,default_duration_min', 'therapyEnrollment.therapist:id,name,status', 'branch:id,name'])
            ->where('patient_id', $patient->id)->where('type', EnrollmentType::Therapy)->where('status', EnrollmentStatus::Active)->get();
    }

    public function options(Request $request, Patient $patient): JsonResponse
    {
        $this->child($request, $patient);
        $s = $this->settings->group('appointment');

        return response()->json(['data' => [
            'enabled' => $s['portal_booking_enabled'] === '1', 'cancel_enabled' => $s['portal_cancel_enabled'] === '1',
            'days_ahead' => (int) $s['portal_booking_days'], 'notice_hours' => (int) $s['portal_booking_notice_hours'], 'late_cancel_hours' => (int) $s['late_cancel_hours'],
            'programmes' => $this->enrollments($patient)->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id, 'service' => $e->therapyEnrollment->service->name, 'service_bn' => $e->therapyEnrollment->service->name_bn,
                'therapist' => $e->therapyEnrollment->therapist->name, 'branch' => $e->branch->name,
            ])->values(),
        ]]);
    }

    public function slots(Request $request, Patient $patient, AvailabilityService $availability): JsonResponse
    {
        $this->child($request, $patient);
        $data = $request->validate(['enrollment_id' => ['required', 'integer'], 'date' => ['required', 'date']]);
        $enrollment = $this->enrollments($patient)->firstWhere('id', (int) $data['enrollment_id']) ?? abort(404);
        $date = Carbon::parse($data['date'])->startOfDay();
        $this->assertWindow($date);
        $te = $enrollment->therapyEnrollment;
        $result = $availability->slots($te->therapist, $enrollment->branch_id, $date, $te->session_duration_min ?? $te->service->default_duration_min ?? 45);
        $earliest = now()->addHours($this->settings->int('appointment', 'portal_booking_notice_hours'));

        return response()->json(['data' => [
            'closed' => $result['closed'],
            'slots' => collect($result['slots'])->filter(fn ($s) => $s['available'] && $date->copy()->setTimeFromTimeString($s['start'])->gte($earliest))->values(),
        ]]);
    }

    public function book(Request $request, Patient $patient, AppointmentService $appointments): JsonResponse
    {
        $this->child($request, $patient);
        abort_unless($this->settings->flag('appointment', 'portal_booking_enabled'), 403, 'অনলাইনে বুকিং এখন বন্ধ আছে।');
        $data = $request->validate(['enrollment_id' => ['required', 'integer'], 'date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i']]);
        $enrollment = $this->enrollments($patient)->firstWhere('id', (int) $data['enrollment_id']) ?? abort(404);
        $date = Carbon::parse($data['date'])->startOfDay();
        $this->assertWindow($date);
        if ($date->copy()->setTimeFromTimeString($data['start_time'])->lt(now()->addHours($this->settings->int('appointment', 'portal_booking_notice_hours')))) {
            throw ValidationException::withMessages(['start_time' => 'এত কাছের সময় অনলাইনে বুক করা যায় না — রিসেপশনে ফোন করুন।']);
        }
        $open = Appointment::where('patient_id', $patient->id)->where('source', 'portal')->where('status', AppointmentStatus::Pending->value)->whereDate('date', '>=', today())->count();
        if ($open >= $this->settings->int('appointment', 'portal_booking_max_open')) {
            throw ValidationException::withMessages(['date' => 'আগের বুকিং নিশ্চিত না হওয়া পর্যন্ত নতুন বুকিং করা যাবে না।']);
        }

        $te = $enrollment->therapyEnrollment;
        $appointment = $appointments->book($patient, [
            'service_id' => $te->service_id, 'therapist_id' => $te->therapist_id, 'branch_id' => $enrollment->branch_id,
            'date' => $date->toDateString(), 'start_time' => $data['start_time'], 'type' => 'therapy',
            'status' => AppointmentStatus::Pending->value, 'source' => 'portal', 'notes' => 'Booked by parent in the portal',
        ], $request->user());
        app(NotificationService::class)->toStaff(Permission::APPOINTMENTS_MANAGE, $enrollment->branch_id, 'appointment.portal_booking',
            "Portal booking: {$patient->name}", "{$te->service->name} with {$te->therapist->name} — {$date->format('D j M')} {$data['start_time']}. Please confirm.", "/app/appointments?date={$date->toDateString()}");

        return response()->json(['data' => ['id' => $appointment->id, 'status' => $appointment->status->value]], 201);
    }

    /** Parents cancel an upcoming appointment of their child; within the late-cancel window it counts as late. */
    public function cancel(Request $request, Patient $patient, Appointment $appointment, AppointmentService $appointments): JsonResponse
    {
        $this->child($request, $patient);
        abort_unless($appointment->patient_id === $patient->id, 404);
        abort_unless($this->settings->flag('appointment', 'portal_cancel_enabled'), 403, 'অনলাইনে বাতিল করা এখন বন্ধ আছে — রিসেপশনে ফোন করুন।');
        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true) || $appointment->startsAt()->isPast()) {
            throw ValidationException::withMessages(['appointment' => 'এই অ্যাপয়েন্টমেন্ট আর বাতিল করা যায় না।']);
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $appointment = $appointments->changeStatus($appointment, 'cancel', ['reason' => 'অভিভাবক বাতিল করেছেন'.(($data['reason'] ?? '') ? ": {$data['reason']}" : '')], $request->user());
        app(NotificationService::class)->toStaff(Permission::APPOINTMENTS_MANAGE, $appointment->branch_id, 'appointment.portal_cancel',
            "Cancelled by parent: {$patient->name}", "{$appointment->date->format('D j M')} ".substr($appointment->start_time, 0, 5).($appointment->is_late_cancellation ? ' — late cancellation' : ''), "/app/appointments?date={$appointment->date->toDateString()}");

        return response()->json(['data' => ['status' => $appointment->status->value, 'late' => (bool) $appointment->is_late_cancellation]]);
    }

    private function assertWindow(Carbon $date): void
    {
        if ($date->lt(today()) || $date->gt(today()->addDays($this->settings->int('appointment', 'portal_booking_days')))) {
            throw ValidationException::withMessages(['date' => 'এই তারিখে অনলাইনে বুক করা যায় না।']);
        }
    }
}
