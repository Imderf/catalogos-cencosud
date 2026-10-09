<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Jumbo', 'Metro', 'Easy', 'Paris']);

        return ['slug' => strtolower($name), 'name' => $name];
    }
}
