<?php

namespace App\Enums;

enum SubtaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Adjustments = 'adjustments';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En proceso',
            self::Adjustments => 'Ajustes',
            self::Completed => 'Completado',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Completed;
    }

    /** @return array<int, array{value: string, label: string, closed: bool}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'closed' => $s->isClosed()], self::cases());
    }
}
