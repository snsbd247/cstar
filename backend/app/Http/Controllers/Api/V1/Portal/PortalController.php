<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TherapySession;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Notifications\WebsiteEnquiryReceived;
use App\Services\AttendanceService;
use App\Services\IdGenerator;
use App\Services\PaymentService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parent portal (Plan §১৫). A parent sees only children where their guardian link allows portal access,
 * and only family-facing fields — parent summaries, home practice, goals, shared reports and bills.
 * Clinical notes, trainer/therapist internal notes and draft or unshared items never leave this controller.
 */
class PortalController extends Controller
{
    /** GET /portal/children */
    public function children(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->myChildren($request)->map(fn (Patient $p) => $this->childCard($p))->values()]);
    }

    /** GET /portal/children/{patient}/home — the home cards (Plan §১৫). */
    public function home(Request $request, Patient $patient, AttendanceService $attendance): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        $training = $this->openEnrollment($patient, EnrollmentType::Training);

        $next = Appointment::with(['service', 'therapist'])->where('patient_id', $patient->id)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->where(fn ($q) => $q->whereDate('date', '>', today())->orWhere(fn ($w) => $w->whereDate('date', today())->where('start_time', '>=', now()->format('H:i:s'))))
            ->orderBy('date')->orderBy('start_time')->first();

        $lastSession = TherapySession::with('service')->where('patient_id', $patient->id)->where('status', 'final')->latest('date')->first();
        $lastRecord = TrainingRecord::where('patient_id', $patient->id)->where('status', 'final')->whereNotNull('parent_note')->latest('date')->first();

        return response()->json(['data' => [
            'child' => $this->childCard($patient),
            'next_appointment' => $next ? $this->appointment($next) : null,
            'attendance' => $training ? $attendance->monthly($training, today())['summary'] : null,
            'due' => round((float) Invoice::where('patient_id', $patient->id)->open()->sum('due_total'), 2),
            'new_reports' => Assessment::where('patient_id', $patient->id)->where('status', 'final')->where('shared_with_parent', true)
                ->where('updated_at', '>=', now()->subDays(30))->count(),
            'latest_note' => collect([
                $lastSession ? ['date' => $lastSession->date->toDateString(), 'from' => $lastSession->service->name, 'text' => $lastSession->parent_summary, 'home_practice' => $lastSession->home_practice] : null,
                $lastRecord ? ['date' => $lastRecord->date->toDateString(), 'from' => 'Regular Training', 'text' => $lastRecord->parent_note, 'home_practice' => null] : null,
            ])->filter()->sortByDesc('date')->first(),
            'updates' => $patient->timelineEvents()->where('visibility', 'parent')->latest('occurred_at')->limit(8)->get()
                ->map(fn ($e) => ['title' => $e->title, 'description' => $e->description, 'at' => $e->occurred_at, 'type' => $e->event_type]),
        ]]);
    }

    /** GET /portal/children/{patient}/schedule — appointments and the weekly class timetable. */
    public function schedule(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        $training = $this->openEnrollment($patient, EnrollmentType::Training)?->loadMissing('trainingEnrollment.trainingGroup.schedules', 'trainingEnrollment.trainer');
        $group = $training?->trainingEnrollment?->trainingGroup;

        $upcoming = Appointment::with(['service', 'therapist', 'branch'])->where('patient_id', $patient->id)
            ->whereDate('date', '>=', today())->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::Rescheduled])
            ->orderBy('date')->orderBy('start_time')->limit(30)->get();
        $past = Appointment::with(['service', 'therapist'])->where('patient_id', $patient->id)
            ->whereDate('date', '<', today())->where('status', '!=', AppointmentStatus::Rescheduled->value)
            ->orderByDesc('date')->limit(15)->get();

        return response()->json(['data' => [
            'upcoming' => $upcoming->map(fn ($a) => $this->appointment($a)),
            'past' => $past->map(fn ($a) => $this->appointment($a)),
            'class' => $group ? [
                'name' => $group->name,
                'trainer' => $training->trainingEnrollment->trainer?->name,
                'days' => $group->schedules->sortBy('weekday')->values()->map(fn ($s) => [
                    'weekday' => $s->weekday, 'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5),
                ]),
            ] : null,
            'requests_enabled' => app(\App\Services\SystemSettings::class)->flag('appointment', 'portal_requests_enabled'),
            'services' => Service::where('is_active', true)->where('is_bookable_online', true)->orderBy('sort_order')->get(['id', 'name', 'name_bn']),
        ]]);
    }

    /** GET /portal/children/{patient}/attendance?month=YYYY-MM */
    public function attendance(Request $request, Patient $patient, AttendanceService $attendance): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        $training = $this->openEnrollment($patient, EnrollmentType::Training, includeEnded: true);
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth() : today()->startOfMonth();

        return response()->json(['data' => $training ? $attendance->monthly($training, $month) : null]);
    }

    /** GET /portal/children/{patient}/progress — goals, recent session/class notes, shared reports. */
    public function progress(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        $enrollments = Enrollment::with(['trainingEnrollment.trainingGroup', 'therapyEnrollment.service', 'therapyEnrollment.therapist',
            'plans' => fn ($q) => $q->where('status', 'active'), 'plans.goals'])
            ->where('patient_id', $patient->id)->whereIn('status', EnrollmentStatus::current())->get();

        $sessions = TherapySession::with(['service', 'therapist'])->where('patient_id', $patient->id)->where('status', 'final')
            ->latest('date')->limit(20)->get()
            ->map(fn ($s) => [
                'kind' => 'therapy', 'date' => $s->date->toDateString(), 'title' => $s->service->name, 'by' => $s->therapist->name,
                'text' => $s->parent_summary, 'home_practice' => $s->home_practice,
            ]);
        $records = TrainingRecord::with('trainer')->where('patient_id', $patient->id)->where('status', 'final')
            ->latest('date')->limit(20)->get()
            ->map(fn ($r) => [
                'kind' => 'training', 'date' => $r->date->toDateString(), 'title' => 'Regular Training', 'by' => $r->trainer?->name,
                'text' => $r->parent_note, 'home_practice' => null, 'performance' => $r->performance,
            ]);

        return response()->json(['data' => [
            'programmes' => $enrollments->map(fn (Enrollment $e) => [
                'type' => $e->type->value,
                'label' => $e->summary(),
                'plans' => $e->plans->map(fn ($plan) => [
                    'title' => $plan->title,
                    'review_date' => $plan->review_date?->toDateString(),
                    'goals' => $plan->goals->whereNotIn('status', ['discontinued'])->values()->map(fn ($g) => [
                        'domain' => $g->domain, 'title' => $g->title, 'target' => $g->target,
                        'progress_percent' => (int) $g->progress_percent, 'status' => $g->status,
                    ]),
                ]),
            ]),
            'notes' => $sessions->concat($records)->sortByDesc('date')->values()->take(20),
            'reports' => Assessment::with(['type', 'therapist'])->where('patient_id', $patient->id)->where('status', 'final')->where('shared_with_parent', true)
                ->latest('date')->get()->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->type->name, 'title_bn' => $a->type->name_bn, 'date' => $a->date->toDateString(),
                    'by' => $a->therapist->name, 'summary' => $a->parent_summary,
                ]),
        ]]);
    }

    /** GET /portal/children/{patient}/billing */
    public function billing(Request $request, Patient $patient, PaymentService $payments): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        $invoices = Invoice::with('items')->where('patient_id', $patient->id)->where('status', '!=', 'draft')->latest('issue_date')->latest('id')->limit(40)->get();

        return response()->json(['data' => [
            'due' => round((float) $invoices->whereIn('status', Invoice::OPEN)->sum('due_total'), 2),
            'advance' => $payments->advanceBalance($patient),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id, 'invoice_no' => $i->invoice_no, 'status' => $i->status, 'issue_date' => $i->issue_date?->toDateString(),
                'due_date' => $i->due_date?->toDateString(), 'total' => (float) $i->total, 'paid_total' => (float) $i->paid_total, 'due_total' => (float) $i->due_total,
                'items' => $i->items->pluck('description'),
            ]),
            'payments' => Payment::where('patient_id', $patient->id)->where('status', 'completed')->latest('paid_at')->limit(30)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id, 'receipt_no' => $p->receipt_no, 'type' => $p->type, 'amount' => (float) $p->amount,
                    'method' => $p->method, 'paid_at' => $p->paid_at,
                ]),
            'packages' => PatientPackage::with('package')->where('patient_id', $patient->id)->whereIn('status', ['active', 'exhausted'])->latest('id')->get()
                ->map(fn ($p) => [
                    'name' => $p->package->name, 'name_bn' => $p->package->name_bn, 'status' => $p->status, 'total_sessions' => $p->total_sessions,
                    'used_sessions' => $p->used_sessions, 'remaining' => $p->remaining(), 'expiry_date' => $p->expiry_date->toDateString(),
                ]),
        ]]);
    }

    /** POST /portal/children/{patient}/appointment-requests — reception calls back to confirm. */
    public function requestAppointment(Request $request, Patient $patient, IdGenerator $ids): JsonResponse
    {
        $this->authorizeChild($request, $patient);
        abort_unless(app(\App\Services\SystemSettings::class)->flag('appointment', 'portal_requests_enabled'), 403, 'অনলাইনে appointment-এর অনুরোধ এখন বন্ধ আছে। অনুগ্রহ করে center-এ ফোন করুন।');
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today', 'before:+90 days'],
            'preferred_time' => ['nullable', Rule::in(array_keys(AppointmentRequest::TIMES))],
            'message' => ['required_without:service_id', 'nullable', 'string', 'max:1000'],
        ], ['message.required_without' => 'লিখুন কী প্রয়োজন, অথবা একটি সেবা বেছে নিন।']);

        $guardian = $request->user()->guardian;
        $req = AppointmentRequest::create([
            ...$data,
            'reference' => $ids->next('appointment_request', 'REQ'),
            'branch_id' => $patient->home_branch_id,
            'parent_name' => $guardian->name,
            'child_name' => $patient->name,
            'phone' => $guardian->phone,
            'email' => $guardian->email,
            'patient_id' => $patient->id,
            'status' => 'new',
            'source' => 'portal',
            'ip_address' => $request->ip(),
        ]);

        $recipients = User::permission(Permission::APPOINTMENT_REQUESTS_MANAGE)->where('status', 'active')
            ->where(fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $patient->home_branch_id))
                ->orWhereHas('roles', fn ($r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();
        Notification::send($recipients, new WebsiteEnquiryReceived('appointment_request', "Parent portal request {$req->reference}",
            "{$guardian->name} for {$patient->name} ({$patient->patient_code})", '/app/online-requests'));

        return response()->json(['data' => ['reference' => $req->reference]], 201);
    }

    /** GET /portal/profile */
    public function profile(Request $request): JsonResponse
    {
        $guardian = $request->user()->guardian;

        return response()->json(['data' => [
            'guardian' => $guardian?->only(['name', 'phone', 'alt_phone', 'email', 'address']),
            'children' => $this->myChildren($request)->map(fn ($p) => [...$p->only(['id', 'name', 'name_bn', 'patient_code']), 'relationship' => $p->pivot->relationship]),
            'requests' => AppointmentRequest::whereIn('patient_id', $this->myChildren($request)->pluck('id'))->where('source', 'portal')
                ->latest()->limit(10)->get(['reference', 'status', 'preferred_date', 'message', 'created_at']),
        ]]);
    }

    // ---- PDFs: only what the family is meant to have ---------------------------------------------

    public function invoicePdf(Request $request, Invoice $invoice, PdfService $pdf): Response
    {
        $this->authorizeChild($request, $invoice->patient);
        abort_if($invoice->status === 'draft', 404);

        return $pdf->response('pdf.invoice', ['invoice' => $invoice->load(['patient.guardians', 'branch', 'items.service', 'allocations.payment'])], 'Invoice', "{$invoice->invoice_no}.pdf");
    }

    public function receiptPdf(Request $request, Payment $payment, PdfService $pdf, PaymentService $payments): Response
    {
        $this->authorizeChild($request, $payment->patient);
        abort_if($payment->status !== 'completed', 404);
        $payment->load(['patient', 'branch', 'receiver', 'allocations.invoice']);

        return $pdf->response('pdf.receipt', [
            'payment' => $payment, 'advance' => $payments->advanceBalance($payment->patient),
            'due' => (float) Invoice::where('patient_id', $payment->patient_id)->open()->sum('due_total'),
        ], $payment->type === 'refund' ? 'Refund Receipt' : 'Money Receipt', "{$payment->receipt_no}.pdf");
    }

    public function assessmentPdf(Request $request, Assessment $assessment, PdfService $pdf): Response
    {
        $this->authorizeChild($request, $assessment->patient);
        abort_unless($assessment->isFinal() && $assessment->shared_with_parent, 404);
        $assessment->load(['patient.homeBranch', 'patient.guardians', 'type', 'therapist', 'branch', 'recommendationItems.service', 'recommendationItems.enrollment']);

        return $pdf->response('pdf.assessment', ['a' => $assessment], 'Assessment Report', "{$assessment->assessment_code}.pdf");
    }

    public function progressReport(Request $request, Patient $patient, PdfService $pdf): Response
    {
        $this->authorizeChild($request, $patient);
        $from = today()->subMonths(3)->startOfDay();
        $to = today()->endOfDay();
        $patient->load([
            'homeBranch',
            'enrollments' => fn ($q) => $q->where(fn ($w) => $w->whereIn('status', ['active', 'on_hold', 'pending'])->orWhere('end_date', '>=', $from)),
            'enrollments.trainingEnrollment.trainingGroup', 'enrollments.trainingEnrollment.trainer',
            'enrollments.therapyEnrollment.service', 'enrollments.therapyEnrollment.therapist',
            'enrollments.plans' => fn ($q) => $q->where('status', 'active'), 'enrollments.plans.goals',
        ]);
        $sessions = TherapySession::with('service')->where('patient_id', $patient->id)->where('status', 'final')->whereBetween('date', [$from, $to])->get()->groupBy(fn ($s) => $s->service->name);
        $records = TrainingRecord::where('patient_id', $patient->id)->where('status', 'final')->whereBetween('date', [$from, $to])->count();
        $assessments = Assessment::with('type')->where('patient_id', $patient->id)->where('status', 'final')->where('shared_with_parent', true)->whereBetween('date', [$from, $to])->get();

        return $pdf->response('pdf.progress', compact('patient', 'from', 'to', 'sessions', 'records', 'assessments'), 'Progress Report', "{$patient->patient_code}-progress.pdf");
    }

    // ---------------------------------------------------------------------------------------------

    private function myChildren(Request $request)
    {
        $guardian = $request->user()->guardian;
        abort_unless($request->user()->can(Permission::PORTAL_ACCESS) && $guardian, 403, 'This account is not a parent account.');

        return $guardian->patients()->wherePivot('can_access_portal', true)
            ->with(['enrollments' => fn ($q) => $q->whereIn('status', EnrollmentStatus::current()),
                'enrollments.trainingEnrollment.trainingGroup', 'enrollments.trainingEnrollment.trainer',
                'enrollments.therapyEnrollment.service', 'enrollments.therapyEnrollment.therapist'])
            ->orderBy('date_of_birth')->get();
    }

    private function authorizeChild(Request $request, Patient $patient): void
    {
        abort_unless($this->myChildren($request)->contains('id', $patient->id), 404);
    }

    private function openEnrollment(Patient $patient, EnrollmentType $type, bool $includeEnded = false): ?Enrollment
    {
        return Enrollment::where('patient_id', $patient->id)->where('type', $type)
            ->when(! $includeEnded, fn ($q) => $q->whereIn('status', EnrollmentStatus::current()))
            ->latest('start_date')->first();
    }

    private function childCard(Patient $p): array
    {
        $p->loadMissing(['enrollments' => fn ($q) => $q->whereIn('status', EnrollmentStatus::current()),
            'enrollments.trainingEnrollment.trainingGroup', 'enrollments.trainingEnrollment.trainer',
            'enrollments.therapyEnrollment.service', 'enrollments.therapyEnrollment.therapist']);
        $training = $p->enrollments->contains(fn ($e) => $e->type === EnrollmentType::Training);
        $therapy = $p->enrollments->contains(fn ($e) => $e->type === EnrollmentType::Therapy);

        return [
            ...$p->only(['id', 'name', 'name_bn', 'patient_code', 'gender']),
            'age' => $p->date_of_birth?->diff(today())->format('%y বছর %m মাস'),
            'has_training' => $training,
            'has_therapy' => $therapy,
            'programmes' => $p->enrollments->map(fn (Enrollment $e) => $e->type === EnrollmentType::Training
                ? ['type' => 'training', 'name' => $e->trainingEnrollment?->trainingGroup?->name, 'with' => $e->trainingEnrollment?->trainer?->name]
                : ['type' => 'therapy', 'name' => $e->therapyEnrollment?->service?->name, 'name_bn' => $e->therapyEnrollment?->service?->name_bn, 'with' => $e->therapyEnrollment?->therapist?->name])->values(),
        ];
    }

    private function appointment(Appointment $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->date->toDateString(),
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'status' => $a->status->value,
            'type' => $a->type,
            'service' => $a->service?->name,
            'service_bn' => $a->service?->name_bn,
            'therapist' => $a->therapist?->name,
            'branch' => $a->relationLoaded('branch') ? $a->branch?->name : null,
        ];
    }
}
