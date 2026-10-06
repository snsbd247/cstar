<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Diagnosis;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Default service catalogue (Plan §১৪, §২৮) and diagnosis list. Prices come later from C-STAR. */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [ServiceCategory::Therapy, 'Speech & Language Therapy', 'স্পিচ অ্যান্ড ল্যাঙ্গুয়েজ থেরাপি', 45],
            [ServiceCategory::Therapy, 'Occupational Therapy', 'অকুপেশনাল থেরাপি', 45],
            [ServiceCategory::Therapy, 'ABA Therapy', 'এবিএ থেরাপি', 60],
            [ServiceCategory::Therapy, 'Oral Placement Therapy', 'ওরাল প্লেসমেন্ট থেরাপি', 45],
            [ServiceCategory::Therapy, 'Special Education', 'বিশেষ শিক্ষা', 60],
            [ServiceCategory::Consultation, 'Parent Guidance', 'অভিভাবক নির্দেশনা', 45],
            [ServiceCategory::Assessment, 'Assessment & Evaluation', 'অ্যাসেসমেন্ট ও মূল্যায়ন', 90],
            [ServiceCategory::Training, 'Functional Training', 'ফাংশনাল ট্রেনিং', null],
            [ServiceCategory::Training, 'Daily Living Skills', 'দৈনন্দিন জীবনযাপনের দক্ষতা', null],
            [ServiceCategory::Training, 'Motor Skills', 'মোটর স্কিল', null],
            [ServiceCategory::Training, 'Communication Skills', 'যোগাযোগ দক্ষতা', null],
            [ServiceCategory::Training, 'Social Skills', 'সামাজিক দক্ষতা', null],
            [ServiceCategory::Training, 'Learning Skills', 'শিখন দক্ষতা', null],
        ];

        foreach ($services as $i => [$category, $name, $nameBn, $duration]) {
            Service::firstOrCreate(['slug' => Str::slug($name)], [
                'category' => $category,
                'name' => $name,
                'name_bn' => $nameBn,
                'default_duration_min' => $duration,
                'is_bookable_online' => $category !== ServiceCategory::Training,
                'sort_order' => $i,
            ]);
        }

        $diagnoses = [
            'Autism Spectrum Disorder (ASD)', 'ADHD', 'Speech & Language Delay', 'Global Developmental Delay',
            'Cerebral Palsy', 'Down Syndrome', 'Intellectual Disability', 'Learning Disability',
            'Hearing Impairment', 'Stuttering', 'Feeding / Swallowing Difficulty', 'Sensory Processing Disorder', 'Other',
        ];

        foreach ($diagnoses as $i => $name) {
            Diagnosis::firstOrCreate(['name' => $name], ['sort_order' => $i]);
        }
    }
}
