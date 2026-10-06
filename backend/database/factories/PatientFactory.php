<?php

namespace Database\Factories;

use App\Enums\PatientStatus;
use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_code' => 'CSTAR-TEST-'.fake()->unique()->numerify('#####'),
            'home_branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-12 years', '-2 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => '017'.fake()->numerify('########'),
            'registration_date' => today(),
            'status' => PatientStatus::Active,
        ];
    }
}
