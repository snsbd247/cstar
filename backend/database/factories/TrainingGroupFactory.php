<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\TrainingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingGroup>
 */
class TrainingGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CL-####')),
            'name' => 'Class '.fake()->unique()->word(),
            'max_students' => 10,
            'status' => 'active',
        ];
    }
}
