<?php

namespace Database\Factories;

use App\Enums\TherapistType;
use App\Models\Branch;
use App\Models\Therapist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Therapist>
 */
class TherapistFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'primary_branch_id' => Branch::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'therapist_type' => TherapistType::SpeechLanguage,
            'status' => 'active',
        ];
    }
}
