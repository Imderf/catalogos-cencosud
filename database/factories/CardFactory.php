<?php

namespace Database\Factories;

use App\Models\BoardList;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'board_list_id' => BoardList::factory(),
            'project_id' => fn (array $a) => BoardList::find($a['board_list_id'])->project_id,
            'title' => fake()->sentence(3),
            'position' => 1024,
            'priority' => 'medium',
        ];
    }
}
