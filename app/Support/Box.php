<?php

namespace App\Support;

use InvalidArgumentException;

/** A rectangle over a page image, in fractions of width and height (0..1). */
final class Box
{
    public function __construct(public float $x, public float $y, public float $w, public float $h)
    {
        foreach (['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h] as $k => $v) {
            if ($v < 0 || $v > 1) {
                throw new InvalidArgumentException("Box {$k} must be between 0 and 1.");
            }
        }
        if ($x + $w > 1.0001 || $y + $h > 1.0001) {
            throw new InvalidArgumentException('Box exceeds the page.');
        }
    }

    public static function fromArray(array $a): self
    {
        return new self((float) ($a['x'] ?? 0), (float) ($a['y'] ?? 0), (float) ($a['w'] ?? 0), (float) ($a['h'] ?? 0));
    }

    /** Where a defect marker lands when the reviewer did not draw one. */
    public static function defectDefault(): self
    {
        return new self(0.08, 0.08, 0.30, 0.12);
    }

    public function toArray(): array
    {
        return ['x' => round($this->x, 4), 'y' => round($this->y, 4), 'w' => round($this->w, 4), 'h' => round($this->h, 4)];
    }
}
