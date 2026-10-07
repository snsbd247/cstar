<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Clinical review (Sprint 22 — Plan #২০). A therapist marked "clinical supervisor" reviews colleagues' finalized
 * session notes and assessments in their branches. "Needs changes" tells the author, who corrects the note with an
 * amendment (a final note is never edited). Everyone who can read the note sees the review.
 */
class ClinicalReviewController extends Controller
{
    /** GET /clinical-reviews?status=pending|reviewed */
    public function index(Request $request): JsonResponse
    {
        $user = $this->supervisor($request);
        $branches = $user->accessibleBranchIds();
        $own = $user->therapist?->id;
        $reviewed = $request->input('status') === 'reviewed';
        $since = today()->subDays(60);
        $scope = fn ($q) => $q->where('status', 'final')->whereDate('date', '>=', $since)
            ->when($branches !== null, fn ($b) => $b->whereIn('branch_id', $branches))
            ->when($own, fn ($o) => $o->where('therapist_id', '!=', $own))
            ->{$reviewed ? 'whereHas' : 'whereDoesntHave'}('reviews');

        $sessions = $scope(TherapySession::with(['patient:id,name,patient_code', 'therapist:id,name', 'service:id,name', 'reviews.reviewer:id,name']))
            ->latest('date')->limit(100)->get()->map(fn (TherapySession $s) => [
                'type' => 'session', 'id' => $s->id, 'link_id' => $s->appointment_id, 'date' => $s->date->toDateString(),
                'patient' => $s->patient->only(['id', 'name', 'patient_code']), 'therapist' => $s->therapist?->name, 'title' => $s->service?->name,
                'summary' => $s->parent_summary, 'review' => $s->reviews->sortByDesc('id')->first()?->toRow(),
            ]);
        $assessments = $scope(Assessment::with(['patient:id,name,patient_code', 'therapist:id,name', 'type:id,name', 'reviews.reviewer:id,name']))
            ->latest('date')->limit(100)->get()->map(fn (Assessment $a) => [
                'type' => 'assessment', 'id' => $a->id, 'link_id' => $a->id, 'date' => $a->date->toDateString(),
                'patient' => $a->patient->only(['id', 'name', 'patient_code']), 'therapist' => $a->therapist?->name, 'title' => $a->type?->name,
                'summary' => $a->summary, 'review' => $a->reviews->sortByDesc('id')->first()?->toRow(),
            ]);

        return response()->json(['data' => $sessions->concat($assessments)->sortByDesc('date')->values()]);
    }

    /** POST /clinical-reviews — {type, id, outcome, comment} */
    public function store(Request $request, NotificationService $notify): JsonResponse
    {
        $user = $this->supervisor($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['session', 'assessment'])],
            'id' => ['required', 'integer'],
            'outcome' => ['required', Rule::in(['ok', 'needs_changes'])],
            'comment' => ['nullable', 'required_if:outcome,needs_changes', 'string', 'max:1000'],
        ], ['comment.required_if' => 'Say what needs to change.']);
        $record = $this->record($data['type'], (int) $data['id']);
        abort_unless($record->status === 'final', 422, 'Only finalized notes are reviewed.');
        abort_if($record->therapist_id === $user->therapist?->id, 422, 'You cannot review your own note.');
        $this->authorizeRead($record);

        $review = $record->reviews()->create(['reviewer_id' => $user->id, 'outcome' => $data['outcome'], 'comment' => $data['comment'] ?? null]);
        if ($data['outcome'] === 'needs_changes' && ($author = $record->therapist?->user)) {
            $what = $record instanceof TherapySession ? "session note ({$record->date->format('j M')})" : 'assessment';
            $url = $record instanceof TherapySession ? "/therapist/session/{$record->appointment_id}" : "/therapist/assessments/{$record->id}";
            $notify->send($author, 'clinical.review', "Review: please amend a {$what}", "{$user->name}: {$data['comment']}", $url);
        }

        return response()->json(['data' => $review->setRelation('reviewer', $user)->toRow()], 201);
    }

    /** GET /clinical-reviews/{type}/{id} — the review history shown under a note. */
    public function forRecord(string $type, int $id): JsonResponse
    {
        abort_unless(in_array($type, ['session', 'assessment'], true), 404);
        $record = $this->record($type, $id);
        $this->authorizeRead($record);

        return response()->json(['data' => $record->reviews()->with('reviewer:id,name')->latest('id')->get()->map->toRow()]);
    }

    private function supervisor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->therapist?->is_supervisor, 403, 'Only a clinical supervisor reviews notes.');

        return $user;
    }

    private function record(string $type, int $id): Model
    {
        return $type === 'session' ? TherapySession::with('therapist.user')->findOrFail($id) : Assessment::with('therapist.user')->findOrFail($id);
    }

    private function authorizeRead(Model $record): void
    {
        $record instanceof TherapySession ? Gate::authorize('viewSession', $record->appointment) : Gate::authorize('view', $record);
    }
}
