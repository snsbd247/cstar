<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/** Placeholder head-office branch; real branch details will come from C-STAR. */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(['code' => 'HQ'], [
            'name' => 'C-STAR Head Office',
            'slug' => 'head-office',
            'is_active' => true,
            'show_on_website' => true,
            'opening_hours' => [
                'saturday' => '09:00-18:00', 'sunday' => '09:00-18:00', 'monday' => '09:00-18:00',
                'tuesday' => '09:00-18:00', 'wednesday' => '09:00-18:00', 'thursday' => '09:00-18:00',
                'friday' => null,
            ],
        ]);
    }
}
