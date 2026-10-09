<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'number' => 1,
            'image_path' => 'catalogos/demo/p01-v1.jpg',
        ];
    }
}
