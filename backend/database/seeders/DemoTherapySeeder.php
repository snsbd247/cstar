<?php

namespace Database\Seeders;

use App\Enums\EnrollmentType;
use App\Models\ActivityType;
use App\Models\Enrollment;
use App\Models\Therapist;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\IndividualPlanService;
use App\Services\TherapySessionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Local demo for Sprint 8: therapist working hours, weekly slots for Ayan (Speech + OT) and Sara (Speech),
 * two weeks of past sessions with notes, one no-show, today's appointments and the next four weeks booked.
 */
class DemoTherapySeeder extends Seeder
{
    public function run(AppointmentService $appointments, TherapySessionService $sessions, IndividualPlanService $plans): void
    {
        $imran = Therapist::where('slug', 'imran-hossain')->first();
        $farhana = Therapist::where('slug', 'farhana-rahman')->first();
        if (! $imran || $imran->schedules()->exists()) {
            return;
        }

        $branchId = $imran->primary_branch_id;
        $admin = User::where('email', 'branchadmin@cstar.test')->firstOrFail();
        $imranUser = User::where('email', 'therapist@cstar.test')->firstOrFail();
        auth()->setUser($admin);

        // Working hours (Friday off). Imran: Sun–Thu afternoons. Farhana: Sat–Wed mornings.
        foreach ([0, 1, 2, 3, 4] as $d) {
            $imran->schedules()->create(['branch_id' => $branchId, 'weekday' => $d, 'start_time' => '15:00', 'end_time' => '19:00', 'slot_minutes' => 45]);
        }
        foreach ([6, 0, 1, 2, 3] as $d) {
            $farhana->schedules()->create(['branch_id' => $branchId, 'weekday' => $d, 'start_time' => '10:00', 'end_time' => '14:00', 'slot_minutes' => 45]);
        }

        $therapy = Enrollment::where('type', EnrollmentType::Therapy)->with(['patient', 'slots', 'therapyEnrollment.service', 'therapyEnrollment.therapist'])->get();
        $find = fn (string $patient, string $service) => $therapy->first(fn ($e) => $e->patient->name === $patient && str_contains($e->therapyEnrollment->service->name, $service));
        $ayanSpeech = $find('Ayan Rahman', 'Speech');
        $ayanOt = $find('Ayan Rahman', 'Occupational');
        $saraSpeech = $find('Sara Islam', 'Speech');

        $slots = [
            [$ayanSpeech, [[0, '16:00'], [2, '16:00']]],
            [$ayanOt, [[6, '11:00'], [1, '11:00']]],
            [$saraSpeech, [[1, '15:00'], [3, '15:00']]],
        ];
        $start = today()->subWeeks(2);
        foreach ($slots as [$enrollment, $times]) {
            $enrollment->update(['start_date' => $start->copy()->subDay()]);
            foreach ($times as [$weekday, $time]) {
                $enrollment->slots()->create(['weekday' => $weekday, 'start_time' => $time]);
            }
        }

        // Sara's speech therapy plan, written by Imran.
        $saraPlan = $plans->create($saraSpeech, [
            'title' => 'Speech & Language Plan — Term 1',
            'start_date' => $start->toDateString(),
            'review_date' => today()->addMonths(2)->toDateString(),
            'goals' => [
                ['domain' => 'Expressive language', 'title' => 'Use two-word phrases', 'target' => 'e.g. "more water", "open door" — 10 times per session', 'progress_percent' => 35],
                ['domain' => 'Articulation', 'title' => 'Produce /p/ /b/ /m/ sounds clearly', 'target' => 'At word level, 80% accuracy', 'progress_percent' => 50],
                ['domain' => 'Receptive language', 'title' => 'Follow two-step instructions', 'target' => 'Without gestures, 4 of 5 trials', 'progress_percent' => 30],
            ],
        ], $imranUser);

        $activities = ActivityType::where('applies_to', 'therapy')->pluck('id', 'name');
        $notes = [
            'Engaged well with picture cards; needed fewer prompts than last week.',
            'Some fatigue in the second half; responded well to movement breaks.',
            'Imitated new sounds spontaneously during play.',
        ];

        // Past two weeks: book, then write final notes (one no-show for Sara).
        for ($day = $start->copy(); $day->lt(today()); $day->addDay()) {
            foreach ($slots as [$enrollment, $times]) {
                $time = collect($times)->first(fn ($t) => $t[0] === $day->dayOfWeek)[1] ?? null;
                if (! $time) {
                    continue;
                }
                $service = $enrollment->therapyEnrollment->service;
                $therapist = $enrollment->therapyEnrollment->therapist;
                try {
                    $appointment = $appointments->book($enrollment->patient, [
                        'service_id' => $service->id, 'therapist_id' => $therapist->id, 'branch_id' => $branchId,
                        'date' => $day->toDateString(), 'start_time' => $time, 'source' => 'recurring',
                    ], $admin);
                } catch (ValidationException) {
                    continue; // holiday etc.
                }

                if ($enrollment->is($saraSpeech) && $day->isSameDay($start->copy()->next(Carbon::WEDNESDAY))) {
                    $appointment->update(['status' => 'no_show']);

                    continue;
                }

                $isImran = $therapist->is($imran);
                $sessions->save($appointment, [
                    'activity_ids' => $isImran
                        ? [$activities['Expressive Language'], $activities['Articulation Practice'], $activities['Play-based Therapy']]
                        : [$activities['Sensory Integration'], $activities['Play-based Therapy']],
                    'goals_worked' => $isImran ? 'Two-word phrases, bilabial sounds' : 'Grip strength, sensory regulation',
                    'observation' => $notes[$day->day % count($notes)],
                    'patient_response' => 'Cooperative, good eye contact for most of the session.',
                    'progress' => 'Gradual improvement compared with the previous session.',
                    'home_practice' => $isImran ? 'Name 5 objects at dinner time; praise every attempt.' : 'Play-dough squeezing 5 minutes daily.',
                    'next_session_plan' => $isImran ? 'Introduce three-word phrases with visual support.' : 'Add bead threading for fine motor control.',
                    'therapist_notes' => 'Demo clinical note (internal).',
                    'parent_summary' => 'Good session today. Please continue the home practice every day.',
                    'goal_scores' => $enrollment->is($saraSpeech)
                        ? $saraPlan->goals->map(fn ($g, $i) => ['goal_id' => $g->id, 'score' => min(5, 2 + (($day->day + $i) % 3))])->values()->all()
                        : [],
                    'finalize' => true,
                ], $isImran ? $imranUser : $admin);
            }
        }

        // Today and the next four weeks from the weekly slots.
        foreach ([$ayanSpeech, $ayanOt, $saraSpeech] as $enrollment) {
            $time = $enrollment->slots()->where('weekday', today()->dayOfWeek)->value('start_time');
            if ($time) {
                try {
                    $appointments->book($enrollment->patient, [
                        'service_id' => $enrollment->therapyEnrollment->service_id, 'therapist_id' => $enrollment->therapyEnrollment->therapist_id,
                        'branch_id' => $branchId, 'date' => today()->toDateString(), 'start_time' => substr($time, 0, 5), 'source' => 'recurring',
                    ], $admin);
                } catch (ValidationException) {
                    // today is a holiday or the slot has passed
                }
            }
            $appointments->generateRecurring($enrollment->fresh(), $admin);
        }
    }
}
