<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

/** One login per role for local development. Password for all: Cstar@1234 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Cstar@1234';

    public function run(): void
    {
        $branch = Branch::where('code', 'HQ')->firstOrFail();

        $users = [
            [Role::SuperAdmin, 'Super Admin', 'admin@cstar.test', '01700000001'],
            [Role::BranchAdmin, 'Branch Admin', 'branchadmin@cstar.test', '01700000002'],
            [Role::Receptionist, 'Receptionist', 'reception@cstar.test', '01700000003'],
            [Role::Trainer, 'Md. Hasan (Trainer)', 'trainer@cstar.test', '01700000004'],
            [Role::Therapist, 'Imran (Therapist)', 'therapist@cstar.test', '01700000005'],
            [Role::Accountant, 'Accountant', 'accounts@cstar.test', '01700000006'],
            [Role::Parent, 'Ayan\'s Mother (Parent)', null, '01700000007'],
        ];

        foreach ($users as [$role, $name, $email, $phone]) {
            $user = User::updateOrCreate(['phone' => $phone], [
                'name' => $name,
                'email' => $email,
                'password' => self::PASSWORD,
                'user_type' => $role === Role::Parent ? UserType::Parent : UserType::Staff,
                'status' => UserStatus::Active,
            ]);

            $user->syncRoles([$role->value]);
            $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
        }
    }
}
