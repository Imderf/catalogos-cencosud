<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardListFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'name' => 'Lista', 'position' => 1024];
    }
}
