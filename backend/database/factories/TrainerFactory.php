<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'branch_id' => Branch::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
        ];
    }
}
