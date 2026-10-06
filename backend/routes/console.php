<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
