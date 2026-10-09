<?php

namespace Database\Factories;

use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubtaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'project_id' => fn (array $a) => Card::find($a['card_id'])->project_id,
            'title' => fake()->sentence(3),
            'position' => 1024,
        ];
    }
}
