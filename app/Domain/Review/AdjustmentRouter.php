<?php

namespace App\Domain\Review;

use App\Enums\Role;

/**
 * Adjustments DESCEND: born at any client level, they go down link by link to Ogilvy,
 * who executes them. comercial → planificación → marketing → agencia.
 */
final class AdjustmentRouter
{
    /** @return list<string> */
    public static function routeFrom(Role $origin): array
    {
        $chain = array_map(fn (Role $r) => $r->value, Role::chain());
        $i = array_search($origin->value, $chain, true);
        if ($i === false || $i === 0) {
            return [Role::Agencia->value];
        }

        return [...array_reverse(array_slice($chain, 0, $i)), Role::Agencia->value];
    }

    /** Full trace for display: origin followed by every link it passes through. */
    public static function trace(Role $origin): array
    {
        return [$origin->value, ...self::routeFrom($origin)];
    }
}
