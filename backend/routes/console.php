<?php

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\IndividualPlan;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BackupService;
use App\Services\ChargeService;
use App\Services\ExpenseService;
use App\Services\NotificationService;
use App\Services\PackageService;
use App\Services\SystemSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cstar:create-admin {--name=} {--email=} {--phone=}', function () {
    $data = [
        'name' => $this->option('name') ?: $this->ask('Name'),
        'email' => $this->option('email') ?: $this->ask('Email'),
        'phone' => $this->option('phone') ?: $this->ask('Mobile (01XXXXXXXXX)'),
        'password' => $this->secret('Password (letters + numbers; length as in Settings → Security)'),
    ];

    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
        'password' => ['required', Password::defaults()],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::create([...$validator->validated(), 'status' => UserStatus::Active]);
    $user->assignRole(Role::SuperAdmin->value);
    $user->branches()->sync(Branch::pluck('id'));

    $this->info("Super Admin {$user->email} created.");

    return 0;
})->purpose('Create a C-STAR Super Admin account (use on the production server)');

// Books the next N weeks (Settings → Appointment, default 4) of every active therapy enrollment's weekly slots (Plan §১৬ "Recurring").
// Production runs this from the single cPanel cron: * * * * * php artisan schedule:run
Artisan::command('cstar:generate-appointments {--weeks=}', function (AppointmentService $appointments) {
    $weeks = (int) ($this->option('weeks') ?: SystemSettings::safe('appointment', 'recurring_weeks'));
    $system = User::role(Role::SuperAdmin->value)->orderBy('id')->firstOrFail();
    $created = 0;

    Enrollment::where('type', EnrollmentType::Therapy)->where('status', EnrollmentStatus::Active)->has('slots')
        ->each(function (Enrollment $enrollment) use ($appointments, $system, &$created) {
            $result = $appointments->generateRecurring($enrollment, $system, $weeks);
            $created += $result['created'];
            foreach ($result['skipped'] as $reason) {
                $this->warn("{$enrollment->enrollment_code} skipped {$reason}");
            }
        });

    $this->info("Created {$created} appointments.");
})->purpose('Create upcoming appointments from therapy enrollments\' weekly slots');

Schedule::command('cstar:generate-appointments')->dailyAt('01:00');

// Regular Training monthly fee invoices on the 1st (decision D4: fixed monthly fee, never twice for a month).
Artisan::command('cstar:training-fees {--month=}', function (ChargeService $charges) {
    $system = User::role(Role::SuperAdmin->value)->orderBy('id')->firstOrFail();
    $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth() : today()->startOfMonth();
    $created = $charges->generateTrainingFees($month, $system);
    $this->info("Created {$created} training fee invoices for {$month->format('F Y')}.");
})->purpose('Create this month\'s Regular Training fee invoices');

// Packages past their expiry: recognise the unused value as income (decision A2).
Artisan::command('cstar:expire-packages', function (PackageService $packages) {
    $this->info('Expired '.$packages->expireDue().' packages.');
})->purpose('Close expired therapy packages');

Schedule::command('cstar:training-fees')->monthlyOn(1, '02:00');
Schedule::command('cstar:expire-packages')->dailyAt('00:30');

// Rent, internet… raised as draft expenses on their day each month for the accountant to check (Accounts §৫).
Artisan::command('cstar:recurring-expenses', function (ExpenseService $expenses) {
    $system = User::role(Role::SuperAdmin->value)->orderBy('id')->firstOrFail();
    $this->info('Raised '.$expenses->generateRecurring($system).' recurring expenses.');
})->purpose('Raise this month\'s recurring expenses as drafts');

Schedule::command('cstar:recurring-expenses')->dailyAt('06:00');

// Plan §১৬ "দিনের আগে Reminder": parents hear about tomorrow's appointments; therapists about notes left open.
Artisan::command('cstar:reminders {--type=all : parents | staff | all}', function (NotificationService $notify) {
    $type = $this->option('type');
    if (in_array($type, ['parents', 'all'], true) && $notify->enabled('parent_reminders')) {
        $sent = 0;
        Appointment::with(['patient', 'service', 'therapist'])->whereDate('date', today()->addDay())
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])->get()
            ->each(function (Appointment $a) use ($notify, &$sent) {
                $sent += $notify->parentsTemplate($a->patient, 'appointment.reminder', [
                    'service' => $a->service->name_bn ?: $a->service->name, 'time' => NotificationService::bnTime(substr($a->start_time, 0, 5)), 'therapist' => $a->therapist->name,
                ], '/portal/schedule');
            });
        $this->info("Parent reminders: {$sent}");
    }
    if (in_array($type, ['staff', 'all'], true) && $notify->enabled('staff_reminders')) {
        $sent = 0;
        Appointment::with(['therapist.user', 'session'])->whereDate('date', '<', today())->whereDate('date', '>=', today()->subDays(14))
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value])->where('type', 'therapy')
            ->get()->filter(fn ($a) => $a->session?->status !== 'final' && $a->therapist->user)
            ->groupBy('therapist_id')
            ->each(function ($list) use ($notify, &$sent) {
                $sent += $notify->send($list->first()->therapist->user, 'notes.pending', "{$list->count()} session note(s) to finalize",
                    'Finalize them so families see the summary and billing stays correct.', '/therapist');
            });
        $this->info("Therapist reminders: {$sent}");

        // Sprint 22: plans (ITP / therapy plan) whose review date is near or past — once a week per plan until reviewed.
        $sent = 0;
        IndividualPlan::with(['patient:id,name', 'enrollment.therapyEnrollment.therapist.user', 'enrollment.trainingEnrollment.trainer.user'])
            ->where('status', 'active')->whereNotNull('review_date')->whereDate('review_date', '<=', today()->addDays(7))
            ->get()->filter(fn (IndividualPlan $p) => Cache::add("plan-review-reminded:{$p->id}", true, now()->addDays(7)))
            ->groupBy(fn (IndividualPlan $p) => ($p->enrollment?->therapyEnrollment?->therapist?->user ?? $p->enrollment?->trainingEnrollment?->trainer?->user)?->id)
            ->each(function ($plans, $userId) use ($notify, &$sent) {
                $user = $userId ? User::find($userId) : null;
                if (! $user) {
                    return;
                }
                $names = $plans->map(fn ($p) => "{$p->patient->name} ({$p->review_date->format('j M')})")->take(5)->implode(', ');
                $sent += $notify->send($user, 'plans.review_due', "{$plans->count()} plan(s) due for review", "{$names} — review the goals and set the next review date.",
                    $user->hasRole(Role::Trainer->value) ? '/trainer/students' : '/therapist/patients');
            });
        $this->info("Plan review reminders: {$sent}");
    }
})->purpose('Send appointment reminders to parents, and pending-note and plan-review reminders to staff');

Schedule::command('cstar:reminders --type=parents')->dailyAt('18:00');
Schedule::command('cstar:reminders --type=staff')->dailyAt('08:00');

// Plan §২১ Backup: database dump every night (Settings → Backup), old files removed after N days.
Artisan::command('cstar:backup {--force : Run even when the daily backup is turned off}', function (BackupService $backups, SystemSettings $settings) {
    if (! $this->option('force') && ! $settings->flag('backup', 'daily_enabled')) {
        $this->info('Daily backup is turned off in Settings.');

        return;
    }
    $file = $backups->create();
    $removed = $backups->prune($settings->int('backup', 'keep_days'));
    $this->info("Backup {$file['name']} (".number_format($file['size'] / 1024, 1)." KB); removed {$removed} old file(s).");
})->purpose('Back up the database to storage/app/private/backups');

Schedule::command('cstar:backup')->dailyAt('02:30');

// Emails wait in the database queue; shared hosting has no long-running worker, so cron empties it each minute.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping();

// Settings → System shows when the scheduler (cPanel cron) last ran, so a missing cron job is noticed.
Schedule::call(fn () => Cache::forever('scheduler.heartbeat', now()->toIso8601String()))->everyMinute()->name('scheduler-heartbeat');
