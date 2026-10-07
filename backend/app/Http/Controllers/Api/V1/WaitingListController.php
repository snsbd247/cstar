<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\WaitingListEntry;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Enrollments → Waiting List (Sprint 20). */
class WaitingListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $status = $request->input('status', 'waiting');
        $entries = WaitingListEntry::with(['patient:id,name,patient_code,phone,date_of_birth', 'branch:id,name', 'service:id,name', 'trainingGroup:id,name', 'creator:id,name'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByRaw("priority = 'high' DESC")->orderBy('created_at')->get();

        // Position = place in line among the same kind of wait (branch + class / service).
        $counters = [];

        return response()->json(['data' => $entries->map(function (WaitingListEntry $e) use (&$counters) {
            $key = "{$e->branch_id}|{$e->type}|".($e->type === 'training' ? $e->training_group_id : $e->service_id);
            $position = in_array($e->status, ['waiting', 'offered'], true) ? ($counters[$key] = ($counters[$key] ?? 0) + 1) : null;

            return [
                'id' => $e->id, 'type' => $e->type, 'status' => $e->status, 'priority' => $e->priority, 'preferred_time' => $e->preferred_time,
                'notes' => $e->notes, 'position' => $position, 'waiting_days' => (int) $e->created_at->diffInDays(now()),
                'patient' => $e->patient->only(['id', 'name', 'patient_code', 'phone']), 'branch' => $e->branch->name,
                'wants' => $e->type === 'training' ? ($e->trainingGroup?->name ?? 'Any training class') : ($e->service?->name ?? 'Therapy'),
                'created_at' => $e->created_at->toIso8601String(), 'by' => $e->creator?->name, 'resolution' => $e->resolution,
                'offered_at' => $e->offered_at?->toIso8601String(),
            ];
        })->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_MANAGE);
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type' => ['required', Rule::in(['training', 'therapy'])],
            'service_id' => ['nullable', 'required_if:type,therapy', 'integer', 'exists:services,id'],
            'training_group_id' => ['nullable', 'integer', 'exists:training_groups,id'],
            'preferred_time' => ['required', Rule::in(['morning', 'afternoon', 'evening', 'any'])],
            'priority' => ['required', Rule::in(['normal', 'high'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id'])
            && Patient::visibleTo($request->user())->whereKey($data['patient_id'])->exists(), 403);
        $duplicate = WaitingListEntry::where('patient_id', $data['patient_id'])->where('type', $data['type'])->whereIn('status', ['waiting', 'offered'])
            ->when($data['type'] === 'therapy', fn ($q) => $q->where('service_id', $data['service_id']))->exists();
        if ($duplicate) {
            return response()->json(['message' => 'This child is already waiting for this.', 'errors' => ['patient_id' => ['This child is already on the waiting list for this.']]], 422);
        }

        return response()->json(['data' => WaitingListEntry::create([...$data, 'status' => 'waiting', 'created_by' => $request->user()->id])], 201);
    }

    /** offer (tells the family a place is free) · remove · back to waiting · change priority / notes. */
    public function update(Request $request, WaitingListEntry $entry): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_MANAGE);
        abort_unless($request->user()->canAccessBranch($entry->branch_id), 403);
        $data = $request->validate([
            'action' => ['nullable', Rule::in(['offer', 'remove', 'wait'])],
            'reason' => ['nullable', 'string', 'max:200'],
            'priority' => ['nullable', Rule::in(['normal', 'high'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $changes = array_filter(['priority' => $data['priority'] ?? null, 'notes' => $data['notes'] ?? null], fn ($v) => $v !== null);
        match ($data['action'] ?? null) {
            'offer' => $changes += ['status' => 'offered', 'offered_at' => now()],
            'remove' => $changes += ['status' => 'removed', 'resolved_at' => now(), 'resolution' => $data['reason'] ?? 'Removed'],
            'wait' => $changes += ['status' => 'waiting', 'offered_at' => null, 'resolved_at' => null, 'resolution' => null],
            default => null,
        };
        $entry->update($changes);
        if (($data['action'] ?? null) === 'offer') {
            app(NotificationService::class)->toParents($entry->patient, 'waiting_list.offered', 'জায়গা খালি হয়েছে',
                'অপেক্ষা তালিকা থেকে '.$entry->patient->name.'-এর জন্য জায়গা খালি হয়েছে। রিসেপশন শীঘ্রই যোগাযোগ করবে।', '/portal');
        }

        return response()->json(['data' => $entry->fresh()]);
    }
}
