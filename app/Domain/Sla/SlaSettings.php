<?php

namespace App\Domain\Sla;

use App\Enums\AdjustmentType;
use App\Enums\Role;

/** Deadlines in working minutes. Decision of 24 Sep 2026: five working hours for everything, client and Ogilvy alike. */
final class SlaSettings
{
    public const FIVE_HOURS = 300;

    public const DEFAULT_EXECUTION = [
        'precio' => self::FIVE_HOURS, 'descripcion' => self::FIVE_HOURS, 'imagen' => self::FIVE_HOURS, 'diseno' => self::FIVE_HOURS,
        'dinamica' => self::FIVE_HOURS, 'ingresa_dinamica' => self::FIVE_HOURS, 'compaginada' => self::FIVE_HOURS, 'pata' => self::FIVE_HOURS, 'otro' => self::FIVE_HOURS,
    ];

    public const DEFAULT_VALIDATION = self::FIVE_HOURS;

    public const DEFAULT_APPROVAL = ['marketing' => self::FIVE_HOURS, 'planificacion' => self::FIVE_HOURS, 'comercial' => self::FIVE_HOURS];

    /**
     * @param  array<string,int>  $execution  minutes Ogilvy has to execute, by adjustment type
     * @param  int  $validation  minutes each client link has to validate an adjustment
     * @param  array<string,int>  $approval  minutes each client role has to approve a page
     */
    public function __construct(
        public readonly array $execution = self::DEFAULT_EXECUTION,
        public readonly int $validation = self::DEFAULT_VALIDATION,
        public readonly array $approval = self::DEFAULT_APPROVAL,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            array_map('intval', ($a['execution'] ?? []) + self::DEFAULT_EXECUTION),
            (int) ($a['validation'] ?? self::DEFAULT_VALIDATION),
            array_map('intval', ($a['approval'] ?? []) + self::DEFAULT_APPROVAL),
        );
    }

    public function toArray(): array
    {
        return ['execution' => $this->execution, 'validation' => $this->validation, 'approval' => $this->approval];
    }

    public function executionMinutes(AdjustmentType $type): int
    {
        return $this->execution[$type->value] ?? $this->execution['otro'];
    }

    public function approvalMinutes(Role $role): int
    {
        return $this->approval[$role->value] ?? 240;
    }
}
