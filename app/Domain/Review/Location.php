<?php

namespace App\Domain\Review;

use App\Enums\Role;

/** Who holds the page right now. */
final class Location
{
    public function __construct(
        public readonly ?Role $role,
        public readonly string $label,
        /** true while adjustments are being worked, false while waiting for an approval */
        public readonly bool $working,
        public readonly bool $approved = false,
    ) {}

    public function toArray(): array
    {
        return ['role' => $this->role?->value, 'label' => $this->label, 'working' => $this->working, 'approved' => $this->approved];
    }
}
