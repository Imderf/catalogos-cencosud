<?php

namespace App\Domain\Projects;

use Illuminate\Database\Eloquent\Builder;

/** Float ordering with wide gaps; the client sends midpoints, we renumber when they get too tight. */
final class Positions
{
    public const GAP = 1024.0;

    public static function next(Builder $siblings): float
    {
        return ((float) ($siblings->max('position') ?? 0)) + self::GAP;
    }

    /** Renumber a set when two neighbours are closer than 0.001. */
    public static function rebalanceIfNeeded(Builder $siblings): void
    {
        $items = (clone $siblings)->orderBy('position')->get(['id', 'position']);
        $prev = null;
        $tight = false;
        foreach ($items as $it) {
            if ($prev !== null && ($it->position - $prev) < 0.001) {
                $tight = true;
                break;
            }
            $prev = $it->position;
        }
        if (! $tight) {
            return;
        }
        $pos = self::GAP;
        foreach ($items as $it) {
            (clone $siblings)->whereKey($it->id)->update(['position' => $pos]);
            $pos += self::GAP;
        }
    }
}
