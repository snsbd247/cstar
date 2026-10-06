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

        // Clinical activities used only in therapy session notes.
        $therapy = [
            ['Articulation Practice', 'উচ্চারণ অনুশীলন'],
            ['Receptive Language', 'ভাষা বোঝা'],
            ['Expressive Language', 'ভাষা প্রকাশ'],
            ['Oral Motor Exercise', 'মুখের পেশির ব্যায়াম'],
            ['Sensory Integration', 'সেন্সরি ইন্টিগ্রেশন'],
            ['Play-based Therapy', 'খেলাভিত্তিক থেরাপি'],
            ['Behaviour Support', 'আচরণগত সহায়তা'],
            ['Feeding Therapy', 'খাওয়ানোর থেরাপি'],
            ['Parent Coaching', 'অভিভাবক প্রশিক্ষণ'],
        ];

        foreach ($therapy as $i => [$name, $nameBn]) {
            ActivityType::firstOrCreate(['name' => $name], ['name_bn' => $nameBn, 'applies_to' => 'therapy', 'sort_order' => 100 + $i]);
        }
    }
}
