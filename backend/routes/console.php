<?php

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
        'password' => $this->secret('Password (min 8, letters + numbers)'),
    ];

    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
        'password' => ['required', Password::min(8)->letters()->numbers()],
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

// Books the next four weeks of every active therapy enrollment's weekly slots (Plan §১৬ "Recurring").
// Production runs this from the single cPanel cron: * * * * * php artisan schedule:run
Artisan::command('cstar:generate-appointments {--weeks=4}', function (AppointmentService $appointments) {
    $system = User::role(Role::SuperAdmin->value)->orderBy('id')->firstOrFail();
    $created = 0;

    Enrollment::where('type', EnrollmentType::Therapy)->where('status', EnrollmentStatus::Active)->has('slots')
        ->each(function (Enrollment $enrollment) use ($appointments, $system, &$created) {
            $result = $appointments->generateRecurring($enrollment, $system, (int) $this->option('weeks'));
            $created += $result['created'];
            foreach ($result['skipped'] as $reason) {
                $this->warn("{$enrollment->enrollment_code} skipped {$reason}");
            }
        });

    $this->info("Created {$created} appointments.");
})->purpose('Create upcoming appointments from therapy enrollments\' weekly slots');

Schedule::command('cstar:generate-appointments')->dailyAt('01:00');
