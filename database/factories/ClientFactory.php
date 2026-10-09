<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'color' => '#0057B8', 'is_active' => true];
    }
}
