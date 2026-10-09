<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Proyecto '.fake()->unique()->numberBetween(1, 9999),
            'color' => '#EF3B3A',
            'owner_id' => User::factory(),
        ];
    }
}
