<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClassSubstitute;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Substitutes (Sprint 22 — Plan #৬). A therapist's booked appointments for some days move to a colleague who gives
 * the same service and is free at that time (the original therapist is remembered, the family is told). A trainer
 * covers a class for some days with the class trainer's access for those days only.
 */
class SubstituteController extends Controller
{
    /** GET /therapists/{therapist}/substitute?from=&to= — the appointments to move and colleagues who could take them. */
    public function therapistOptions(Request $request, Therapist $therapist): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENTS_MANAGE);
        [$from, $to] = $this->range($request);
        $appointments = $this->appointments($request, $therapist, $from, $to);
        $serviceIds = $appointments->pluck('service_id')->unique();

        $candidates = Therapist::with('services:id')->where('status', 'active')->whereKeyNot($therapist->id)
            ->whereHas('services', fn ($s) => $s->whereIn('services.id', $serviceIds))->orderBy('name')->get()
            ->map(fn (Therapist $t) => ['id' => $t->id, 'name' => $t->name, 'can_take' => $appointments->filter(fn ($a) => $this->problem($a, $t) === null)->count()]);

        return response()->json(['data' => [
            'appointments' => $appointments->map(fn (Appointment $a) => [
                'id' => $a->id, 'date' => $a->date->toDateString(), 'start_time' => substr($a->start_time, 0, 5), 'end_time' => substr($a->end_time, 0, 5),
                'patient' => $a->patient->name, 'service' => $a->service->name, 'status' => $a->status->value,
            ])->values(),
            'candidates' => $candidates->values(),
        ]]);
    }

    /** POST /therapists/{therapist}/substitute — {from, to, substitute_id, dry_run?} */
    public function assignTherapist(Request $request, Therapist $therapist, NotificationService $notify): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENTS_MANAGE);
        [$from, $to] = $this->range($request);
        $data = $request->validate([
            'substitute_id' => ['required', 'integer', Rule::exists('therapists', 'id')->where('status', 'active'), Rule::notIn([$therapist->id])],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $substitute = Therapist::with('services:id', 'user')->findOrFail($data['substitute_id']);
        $moved = [];
        $skipped = [];

        foreach ($this->appointments($request, $therapist, $from, $to) as $a) {
            $row = ['id' => $a->id, 'date' => $a->date->toDateString(), 'time' => substr($a->start_time, 0, 5), 'patient' => $a->patient->name];
            if ($problem = $this->problem($a, $substitute)) {
                $skipped[] = [...$row, 'reason' => $problem];

                continue;
            }
            if (! $request->boolean('dry_run')) {
                try {
                    DB::transaction(fn () => $a->update([
                        'therapist_id' => $substitute->id,
                        'substitute_for_id' => $a->substitute_for_id ?? $therapist->id,
                        'notes' => trim(($a->notes ? "{$a->notes}\n" : '')."Substitute for {$therapist->name}."),
                    ]));
                } catch (QueryException) {                                   // the database's own double-booking guard
                    $skipped[] = [...$row, 'reason' => "{$substitute->name} is already booked at this time."];

                    continue;
                }
                $notify->toParents($a->patient, 'appointment.substitute', 'থেরাপিস্ট বদল',
                    "{$a->date->format('d/m/Y')} ".substr($a->start_time, 0, 5)."-এর সেশন নেবেন {$substitute->name} ({$therapist->name} সেদিন ছুটিতে)। সময় একই থাকছে।", '/portal/schedule');
            }
            $moved[] = $row;
        }

        if (! $request->boolean('dry_run') && $moved && $substitute->user) {
            $notify->send($substitute->user, 'appointment.substitute', 'You are covering for '.$therapist->name,
                count($moved)." appointment(s) between {$from->format('j M')} and {$to->format('j M')} are now on your schedule.", '/therapist/schedule');
        }

        return response()->json(['data' => ['moved' => $moved, 'skipped' => $skipped, 'dry_run' => $request->boolean('dry_run')]]);
    }

    public function classSubstitutes(TrainingGroup $class): JsonResponse
    {
        Gate::authorize('view', $class);

        return response()->json(['data' => $class->substitutes()->with('trainer:id,name')->whereDate('date_to', '>=', today()->subDays(30))->orderBy('date_from')->get()
            ->map(fn (ClassSubstitute $s) => [
                'id' => $s->id, 'trainer' => $s->trainer->only(['id', 'name']), 'date_from' => $s->date_from->toDateString(),
                'date_to' => $s->date_to->toDateString(), 'reason' => $s->reason, 'active' => $s->date_from->lte(today()) && $s->date_to->gte(today()),
            ])]);
    }

    public function addClassSubstitute(Request $request, TrainingGroup $class, NotificationService $notify): JsonResponse
    {
        Gate::authorize('update', $class);
        $data = $request->validate([
            'trainer_id' => ['required', 'integer', Rule::exists('trainers', 'id')->where('status', 'active'), Rule::notIn(array_filter([$class->lead_trainer_id]))],
            'date_from' => ['required', 'date', 'after_or_equal:'.today()->subDays(7)->toDateString()],
            'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:'.today()->addDays(90)->toDateString()],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['trainer_id.not_in' => 'This is already the class trainer.']);
        $substitute = $class->substitutes()->create([...$data, 'created_by' => $request->user()->id]);
        if ($user = Trainer::find($data['trainer_id'])?->user) {
            $notify->send($user, 'class.substitute', "You are covering {$class->name}",
                Carbon::parse($data['date_from'])->format('j M').' – '.Carbon::parse($data['date_to'])->format('j M').': attendance and records for this class are open to you.', '/trainer');
        }

        return response()->json(['data' => ['id' => $substitute->id]], 201);
    }

    public function removeClassSubstitute(ClassSubstitute $substitute): JsonResponse
    {
        Gate::authorize('update', $substitute->trainingGroup);
        $substitute->delete();

        return response()->json(null, 204);
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        abort_if($from->diffInDays($to) > 62, 422, 'Choose at most two months.');

        return [$from, $to];
    }

    private function appointments(Request $request, Therapist $therapist, Carbon $from, Carbon $to)
    {
        $branches = $request->user()->accessibleBranchIds();

        return Appointment::with(['patient:id,name', 'service:id,name'])->where('therapist_id', $therapist->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->orderBy('date')->orderBy('start_time')->get();
    }

    /** Why the substitute cannot take this appointment, or null when they can. */
    private function problem(Appointment $a, Therapist $substitute): ?string
    {
        if (! $substitute->services->contains('id', $a->service_id)) {
            return "{$substitute->name} does not give {$a->service->name}.";
        }
        if (TherapistLeave::where('therapist_id', $substitute->id)->whereDate('start_date', '<=', $a->date)->whereDate('end_date', '>=', $a->date)->exists()) {
            return "{$substitute->name} is on leave that day.";
        }
        $clash = Appointment::where('therapist_id', $substitute->id)->whereDate('date', $a->date)->whereIn('status', AppointmentStatus::live())
            ->where('start_time', '<', $a->end_time)->where('end_time', '>', $a->start_time)->exists();

        return $clash ? "{$substitute->name} is already booked at this time." : null;
    }
}
