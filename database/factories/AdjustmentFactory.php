<?php

namespace Database\Factories;

use App\Domain\Review\AdjustmentRouter;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'version' => 1,
            'author_id' => User::factory(),
            'origin_role' => Role::Marketing->value,
            'type' => AdjustmentType::Precio->value,
            'text' => fake()->sentence(),
            'box' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.1],
            'status' => AdjustmentStatus::Open->value,
            'route' => AdjustmentRouter::routeFrom(Role::Marketing),
            'is_defect' => false,
            'stage_since' => now(),
            'received_by_agency_at' => now(),
        ];
    }

    public function from(Role $origin): static
    {
        $route = AdjustmentRouter::routeFrom($origin);

        return $this->state(fn () => [
            'origin_role' => $origin->value, 'route' => $route,
            'received_by_agency_at' => $route[0] === Role::Agencia->value ? now() : null,
        ]);
    }

    public function type(AdjustmentType $type): static
    {
        return $this->state(fn () => ['type' => $type->value]);
    }

    public function resolved(int $inVersion = 1): static
    {
        return $this->state(fn () => ['status' => AdjustmentStatus::Resolved->value, 'resolved_in_version' => $inVersion, 'resolved_at' => now()]);
    }
}
