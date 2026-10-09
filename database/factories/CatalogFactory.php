<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CatalogFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Octavilla '.fake()->unique()->numberBetween(1, 999);

        return [
            'brand_id' => Brand::query()->inRandomOrder()->value('id') ?? Brand::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'published_at' => now(),
        ];
    }
}
