<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            ServiceSeeder::class,
            WebsiteContentSeeder::class,
            ActivityTypeSeeder::class,
            AssessmentTypeSeeder::class,
            ChartOfAccountsSeeder::class,
            ExpenseCategorySeeder::class,
        ]);

        // Demo logins and the Ayan / Sara / Rafi example — never in production
        // (create the real admin with `php artisan cstar:create-admin`).
        if (! app()->isProduction()) {
            $this->call([DemoUserSeeder::class, DemoClinicSeeder::class, DemoWebsiteSeeder::class, DemoTrainingSeeder::class, DemoTherapySeeder::class, DemoAssessmentSeeder::class, DemoBillingSeeder::class, DemoAccountsSeeder::class]);
        }
    }
}
