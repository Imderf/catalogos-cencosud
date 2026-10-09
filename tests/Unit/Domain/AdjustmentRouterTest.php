<?php

use App\Domain\Review\AdjustmentRouter;
use App\Enums\Role;

test('adjustments descend to Ogilvy through every link below their origin', function () {
    expect(AdjustmentRouter::routeFrom(Role::Comercial))->toBe(['planificacion', 'marketing', 'agencia'])
        ->and(AdjustmentRouter::routeFrom(Role::Planificacion))->toBe(['marketing', 'agencia'])
        ->and(AdjustmentRouter::routeFrom(Role::Marketing))->toBe(['agencia'])
        ->and(AdjustmentRouter::routeFrom(Role::Agencia))->toBe(['agencia']);
});

test('the trace shows the origin followed by the route', function () {
    expect(AdjustmentRouter::trace(Role::Comercial))->toBe(['comercial', 'planificacion', 'marketing', 'agencia']);
});
