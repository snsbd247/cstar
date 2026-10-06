<?php

namespace Database\Seeders;

use App\Enums\EnrollmentType;
use App\Models\ActivityType;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\IndividualPlanService;
use App\Services\TrainingRecordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Local demo for Sprint 7: class schedule (Sat–Thu, 10:00–13:00), a holiday, three weeks of attendance,
 * Ayan's ITP with the five goals from Plan §১৩, and a few training records.
 */
class DemoTrainingSeeder extends Seeder
{
    public function run(AttendanceService $attendance, IndividualPlanService $plans, TrainingRecordService $records): void
    {
        $class = TrainingGroup::where('code', 'FDA')->first();
        if (! $class || $class->schedules()->exists()) {
            return;
        }

        $trainerUser = User::where('email', 'trainer@cstar.test')->firstOrFail();
        // Past attendance is outside the trainer's edit window, so the branch admin "corrects" it.
        $admin = User::where('email', 'branchadmin@cstar.test')->firstOrFail();
        auth()->setUser($trainerUser);

        // Saturday–Thursday; Friday is the weekly holiday in Bangladesh.
        foreach ([6, 0, 1, 2, 3, 4] as $weekday) {
            $class->schedules()->create(['weekday' => $weekday, 'start_time' => '10:00', 'end_time' => '13:00']);
        }
        $class->load('schedules');

        // Seed data predates "today", so move enrollments' start back to cover the demo period.
        $start = today()->subWeeks(3)->startOfWeek(Carbon::SATURDAY);
        Enrollment::where('type', EnrollmentType::Training)->update(['start_date' => $start->copy()->subDay()]);

        $holiday = $start->copy()->addDays(9);
        Holiday::create(['date' => $holiday, 'title' => 'Demo public holiday', 'type' => 'public']);

        $roster = $class->rosterOn(today())->with('patient')->get()->keyBy(fn ($e) => $e->patient->name);
        $ayan = $roster['Ayan Rahman'];
        $rafi = $roster['Rafi Ahmed'];

        $plan = $plans->create($ayan, [
            'title' => 'Individual Training Plan — Term 1',
            'start_date' => $start->toDateString(),
            'review_date' => today()->addMonths(2)->toDateString(),
            'goals' => [
                ['domain' => 'Fine motor', 'title' => 'Improve fine motor skill', 'target' => 'Holds a crayon with tripod grip and colours inside a shape', 'baseline_level' => 'Fist grip, scribbles', 'measurement' => '4 of 5 trials', 'progress_percent' => 40],
                ['domain' => 'Daily living', 'title' => 'Improve independent eating', 'target' => 'Eats rice with a spoon with minimal spilling', 'baseline_level' => 'Needs hand-over-hand help', 'measurement' => 'Observed at snack time', 'progress_percent' => 30],
                ['domain' => 'Communication', 'title' => 'Improve communication', 'target' => 'Requests "water", "toilet", "more" with words or gestures', 'measurement' => 'Spontaneous requests per session', 'progress_percent' => 25],
                ['domain' => 'Daily living', 'title' => 'Improve daily living skill', 'target' => 'Puts on shoes and washes hands with one prompt', 'progress_percent' => 50],
                ['domain' => 'Social', 'title' => 'Improve social interaction', 'target' => 'Takes turns in a 2-child game for 5 minutes', 'progress_percent' => 20],
            ],
        ], $trainerUser);

        $activities = ActivityType::pluck('id', 'name');
        $notes = [
            'Good focus in table-top work today.', 'Needed more prompts after break.', 'Enjoyed the ball game with peers.',
            'Ate half the snack independently.', 'Followed two-step instructions twice.',
        ];

        for ($day = $start->copy(); $day->lte(today()); $day->addDay()) {
            if (! $class->scheduleFor($day)) {
                continue;
            }
            $isHoliday = $day->isSameDay($holiday);
            $statusFor = fn (string $name) => $isHoliday ? 'holiday' : match (true) {
                $name === 'Rafi Ahmed' && $day->day % 7 === 3 => 'absent',
                $name === 'Ayan Rahman' && $day->day % 9 === 4 => 'late',
                $name === 'Rafi Ahmed' && $day->day % 11 === 5 => 'leave',
                default => 'present',
            };

            $attendance->mark($class, $day->copy(), [
                ['enrollment_id' => $ayan->id, 'status' => $statusFor('Ayan Rahman'), 'arrival_time' => '10:20'],
                ['enrollment_id' => $rafi->id, 'status' => $statusFor('Rafi Ahmed')],
            ], $admin);

            // Final records for the last few class days (today stays open for the trainer to write).
            if (! $isHoliday && $day->lt(today()) && $day->gte(today()->subDays(6)) && $statusFor('Ayan Rahman') !== 'absent') {
                $records->save($class, $day->copy(), $ayan, [
                    'activity_ids' => [$activities['Fine Motor Activity'], $activities['Daily Living Activity'], $activities['Communication Activity']],
                    'observation' => $notes[$day->day % count($notes)],
                    'performance' => 3 + ($day->day % 2),
                    'progress' => 'Steady progress on grip; still needs support with spoon.',
                    'parent_note' => 'Ayan had a good day. Please practise holding the crayon at home for 5 minutes.',
                    'next_plan' => 'Continue colouring inside shapes; introduce spoon practice with thicker food.',
                    'goal_scores' => $plan->goals->take(3)->map(fn ($g, $i) => ['goal_id' => $g->id, 'score' => min(5, 2 + $i + ($day->day % 2))])->values()->all(),
                    'finalize' => true,
                ], $trainerUser);
            }
        }
    }
}
