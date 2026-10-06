<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

/** Assessment types with their report sections (Plan §১৯). Reference data — safe in production. */
class AssessmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Speech & Language Assessment', 'স্পিচ ও ভাষা মূল্যায়ন', [
                'receptive_language' => 'Receptive language',
                'expressive_language' => 'Expressive language',
                'articulation' => 'Articulation & speech sounds',
                'oral_motor' => 'Oral motor',
                'social_communication' => 'Social communication',
            ]],
            ['Developmental Assessment', 'বিকাশগত মূল্যায়ন', [
                'gross_motor' => 'Gross motor',
                'fine_motor' => 'Fine motor',
                'cognition' => 'Cognition',
                'language' => 'Language',
                'social_emotional' => 'Social & emotional',
                'self_help' => 'Self-help / daily living',
            ]],
            ['Autism-related Assessment', 'অটিজম-সংক্রান্ত মূল্যায়ন', [
                'social_interaction' => 'Social interaction',
                'communication' => 'Communication',
                'repetitive_behaviour' => 'Restricted / repetitive behaviour',
                'sensory' => 'Sensory profile',
                'behaviour' => 'Behaviour',
            ]],
            ['Occupational Therapy Assessment', 'অকুপেশনাল থেরাপি মূল্যায়ন', [
                'sensory_processing' => 'Sensory processing',
                'fine_motor' => 'Fine motor',
                'gross_motor' => 'Gross motor & balance',
                'daily_living' => 'Activities of daily living',
                'handwriting' => 'Pre-writing / handwriting',
            ]],
            ['Communication Assessment', 'যোগাযোগ মূল্যায়ন', [
                'pre_linguistic' => 'Pre-linguistic skills',
                'comprehension' => 'Comprehension',
                'expression' => 'Expression (verbal / non-verbal)',
                'aac' => 'AAC needs',
            ]],
            ['Feeding Assessment', 'খাওয়ানো মূল্যায়ন', [
                'oral_motor' => 'Oral motor',
                'chewing_swallowing' => 'Chewing & swallowing',
                'food_acceptance' => 'Food acceptance / sensory',
                'mealtime_behaviour' => 'Mealtime behaviour',
            ]],
            ['Other Assessment', 'অন্যান্য মূল্যায়ন', [
                'findings' => 'Findings',
            ]],
        ];

        foreach ($types as $i => [$name, $nameBn, $sections]) {
            AssessmentType::firstOrCreate(['name' => $name], [
                'name_bn' => $nameBn,
                'sections' => collect($sections)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }
}
