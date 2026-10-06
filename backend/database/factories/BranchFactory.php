<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Branch';

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => $name,
            'slug' => Str::slug($name),
            'address' => fake()->address(),
            'phone' => '01'.fake()->numerify('#########'),
            'is_active' => true,
            'show_on_website' => true,
        ];
    }
}
