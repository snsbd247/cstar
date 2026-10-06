<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

/** Activity categories for training records (Plan §১১); therapy-specific ones arrive with Sprint 8. */
class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Fine Motor Activity', 'সূক্ষ্ম মোটর কার্যক্রম'],
            ['Gross Motor Activity', 'বৃহৎ মোটর কার্যক্রম'],
            ['Daily Living Activity', 'দৈনন্দিন জীবনযাপন'],
            ['Communication Activity', 'যোগাযোগ কার্যক্রম'],
            ['Social Interaction', 'সামাজিক মেলামেশা'],
            ['Cognitive Activity', 'জ্ঞানীয় কার্যক্রম'],
            ['Physical Activity', 'শারীরিক কার্যক্রম'],
            ['Functional Skill', 'ফাংশনাল দক্ষতা'],
        ];

        foreach ($types as $i => [$name, $nameBn]) {
            ActivityType::firstOrCreate(['name' => $name], ['name_bn' => $nameBn, 'applies_to' => 'both', 'sort_order' => $i]);
        }
    }
}
