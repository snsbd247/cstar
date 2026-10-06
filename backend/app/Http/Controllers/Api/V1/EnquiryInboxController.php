<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\ContactMessage;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Front-desk inbox: online appointment requests and contact messages from the website. */
class EnquiryInboxController extends Controller
{
    public function appointmentRequests(Request $request): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENT_REQUESTS_MANAGE);
        $request->validate(['status' => ['nullable', Rule::in(AppointmentRequest::STATUSES)]]);

        $requests = AppointmentRequest::visibleTo($request->user())
            ->with(['branch:id,name', 'service:id,name', 'preferredTherapist:id,name', 'handler:id,name', 'patient:id,patient_code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($requests->items())->map(fn (AppointmentRequest $r) => [
                ...$r->only(['id', 'reference', 'parent_name', 'child_name', 'child_age_years', 'phone', 'email', 'preferred_time', 'message', 'status', 'internal_note', 'created_at', 'handled_at', 'appointment_id', 'service_id', 'preferred_therapist_id']),
                'preferred_date' => $r->preferred_date?->toDateString(),
                'preferred_time_label' => AppointmentRequest::TIMES[$r->preferred_time] ?? null,
                'branch' => $r->branch?->name,
                'service' => $r->service?->name,
                'preferred_therapist' => $r->preferredTherapist?->name,
                'handled_by' => $r->handler?->name,
                'patient' => $r->patient ? $r->patient->only(['id', 'patient_code', 'name']) : null,
            ]),
            'meta' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'total' => $requests->total()],
            'counts' => AppointmentRequest::visibleTo($request->user())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function updateAppointmentRequest(Request $request, AppointmentRequest $appointmentRequest): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENT_REQUESTS_MANAGE);
        abort_unless($request->user()->canAccessBranch($appointmentRequest->branch_id), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(AppointmentRequest::STATUSES)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'patient_id' => ['nullable', 'required_if:status,converted', 'integer', 'exists:patients,id'],
        ], ['patient_id.required_if' => 'Register the child first, then mark the request as converted.']);

        if (! empty($data['patient_id'])) {
            abort_unless(Patient::visibleTo($request->user())->whereKey($data['patient_id'])->exists(), 403);
        }

        $appointmentRequest->update([...$data, 'handled_by' => $request->user()->id, 'handled_at' => now()]);

        return response()->json(['message' => 'Request updated.']);
    }

    public function contactMessages(Request $request): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENT_REQUESTS_MANAGE);

        $messages = ContactMessage::visibleTo($request->user())->with('branch:id,name')->latest('id')->paginate(20);

        return response()->json([
            'data' => collect($messages->items())->map(fn (ContactMessage $m) => [
                ...$m->only(['id', 'name', 'phone', 'email', 'subject', 'message', 'status', 'created_at']),
                'branch' => $m->branch?->name,
            ]),
            'meta' => ['current_page' => $messages->currentPage(), 'last_page' => $messages->lastPage(), 'total' => $messages->total()],
        ]);
    }

    public function updateContactMessage(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENT_REQUESTS_MANAGE);
        abort_if($contactMessage->branch_id && ! $request->user()->canAccessBranch($contactMessage->branch_id), 403);

        $contactMessage->update([
            ...$request->validate(['status' => ['required', Rule::in(ContactMessage::STATUSES)]]),
            'handled_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Message updated.']);
    }
}
