<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\TimelineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Patients menu lists across all children the user may see (Sprint 16): Guardians, Documents,
 * Consents and Patient Timeline. Editing still happens on each child's profile.
 */
class PatientRecordsController extends Controller
{
    public function guardians(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_VIEW);
        $visible = Patient::visibleTo($request->user())->select('id');
        $q = trim((string) $request->input('q'));

        $page = Guardian::with(['patients' => fn ($p) => $p->whereIn('patients.id', $visible)->select('patients.id', 'name', 'patient_code')])
            ->whereHas('patients', fn ($p) => $p->whereIn('patients.id', $visible))
            ->when($q !== '', fn ($g) => $g->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")
                ->orWhereHas('patients', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%"))))
            ->when($request->input('portal') === 'yes', fn ($g) => $g->whereNotNull('user_id'))
            ->when($request->input('portal') === 'no', fn ($g) => $g->whereNull('user_id'))
            ->orderBy('name')->paginate(30);

        return $this->page($page, fn (Guardian $g) => [
            ...$g->only(['id', 'name', 'phone', 'alt_phone', 'email', 'occupation']),
            'has_portal_account' => $g->user_id !== null,
            'children' => $g->patients->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'patient_code' => $p->patient_code,
                'relationship' => $p->pivot->relationship, 'is_primary' => (bool) $p->pivot->is_primary, 'can_access_portal' => (bool) $p->pivot->can_access_portal,
            ]),
        ]);
    }

    /** Clinical documents (medical reports, prescriptions, assessments) only for users who may see clinical data. */
    public function documents(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_VIEW);
        $q = trim((string) $request->input('q'));

        $page = PatientDocument::with(['patient:id,name,patient_code', 'uploader:id,name'])
            ->whereIn('patient_id', Patient::visibleTo($request->user())->select('id'))
            ->when(! $request->user()->can(Permission::PATIENTS_VIEW_CLINICAL), fn ($d) => $d->whereNotIn('category', PatientDocument::CLINICAL_CATEGORIES))
            ->when($request->filled('category'), fn ($d) => $d->where('category', $request->string('category')))
            ->when($q !== '', fn ($d) => $d->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%"))))
            ->latest()->paginate(30);

        return $this->page($page, fn (PatientDocument $d) => [
            'id' => $d->id, 'title' => $d->title, 'category' => $d->category, 'category_label' => Str::headline($d->category),
            'original_name' => $d->original_name, 'mime' => $d->mime, 'size' => $d->size, 'visible_to_parent' => $d->visible_to_parent,
            'patient' => $d->patient->only(['id', 'name', 'patient_code']), 'uploaded_by' => $d->uploader?->name,
            'created_at' => $d->created_at->toIso8601String(),
        ], ['categories' => collect(PatientDocument::CATEGORIES)
            ->reject(fn ($c) => ! $request->user()->can(Permission::PATIENTS_VIEW_CLINICAL) && in_array($c, PatientDocument::CLINICAL_CATEGORIES, true))
            ->map(fn ($c) => ['value' => $c, 'label' => Str::headline($c)])->values()]);
    }

    /** Consents given or refused, plus children still missing a treatment consent. */
    public function consents(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_VIEW);
        $visible = Patient::visibleTo($request->user());

        if ($request->input('view') === 'missing') {
            $page = (clone $visible)->with('homeBranch:id,name')
                ->whereDoesntHave('consents', fn ($c) => $c->where('type', 'treatment')->where('granted', true))
                ->orderBy('name')->paginate(30);

            return $this->page($page, fn (Patient $p) => [
                'patient' => $p->only(['id', 'name', 'patient_code']), 'branch' => $p->homeBranch?->name, 'registered' => $p->registration_date?->toDateString(),
            ], ['missing' => $page->total()]);
        }

        $page = Consent::with(['patient:id,name,patient_code', 'guardian:id,name'])
            ->whereIn('patient_id', (clone $visible)->select('id'))
            ->when($request->filled('type'), fn ($c) => $c->where('type', $request->string('type')))
            ->when($request->input('granted') !== null && $request->input('granted') !== '', fn ($c) => $c->where('granted', $request->boolean('granted')))
            ->latest('signed_on')->latest('id')->paginate(30);
        $missing = (clone $visible)->whereDoesntHave('consents', fn ($c) => $c->where('type', 'treatment')->where('granted', true))->count();

        return $this->page($page, fn (Consent $c) => [
            'id' => $c->id, 'type' => $c->type, 'type_label' => Consent::LABELS[$c->type] ?? Str::headline($c->type), 'granted' => (bool) $c->granted,
            'signed_on' => $c->signed_on?->toDateString(), 'patient' => $c->patient->only(['id', 'name', 'patient_code']), 'guardian' => $c->guardian?->name,
        ], ['missing' => $missing]);
    }

    /** Patient Timeline across children: registrations, enrollments, sessions, payments … newest first. */
    public function timeline(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_VIEW);

        $page = TimelineEvent::with(['actor:id,name'])
            ->whereIn('patient_id', Patient::visibleTo($request->user())->select('id'))
            ->when($request->filled('patient_id'), fn ($t) => $t->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('event'), fn ($t) => $t->where('event_type', 'like', $request->string('event').'%'))
            ->when($request->filled('from'), fn ($t) => $t->where('occurred_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($t) => $t->where('occurred_at', '<=', $request->date('to')->endOfDay()))
            ->latest('occurred_at')->latest('id')->paginate(40);
        $patients = Patient::whereIn('id', collect($page->items())->pluck('patient_id')->unique())->get(['id', 'name', 'patient_code'])->keyBy('id');

        return $this->page($page, fn (TimelineEvent $e) => [
            'id' => $e->id, 'event_type' => $e->event_type, 'title' => $e->title, 'description' => $e->description,
            'visibility' => $e->visibility, 'actor' => $e->actor?->name, 'occurred_at' => $e->occurred_at->toIso8601String(),
            'patient' => $patients[$e->patient_id]?->only(['id', 'name', 'patient_code']),
        ]);
    }

    private function page(LengthAwarePaginator $page, callable $map, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ...$extra,
        ]);
    }
}
