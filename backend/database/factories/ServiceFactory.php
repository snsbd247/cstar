<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Therapy';

        return [
            'category' => ServiceCategory::Therapy,
            'name' => $name,
            'slug' => Str::slug($name),
            'default_duration_min' => 45,
            'is_active' => true,
        ];
    }
}
