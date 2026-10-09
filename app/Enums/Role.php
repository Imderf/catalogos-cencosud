<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agencia = 'agencia';
    /** Ogilvy people who only look: no uploads, no approvals, no comments. */
    case Lectura = 'lectura';
    case Marketing = 'marketing';
    case Planificacion = 'planificacion';
    case Comercial = 'comercial';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Agencia => 'Agencia · Ogilvy',
            self::Lectura => 'Ogilvy · Solo lectura',
            self::Marketing => 'Marketing',
            self::Planificacion => 'Planificación',
            self::Comercial => 'Sector / Comercial',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Agencia => 'Ogilvy',
            self::Lectura => 'Ogilvy',
            self::Marketing => 'Marketing',
            self::Planificacion => 'Planificación',
            self::Comercial => 'Comercial',
        };
    }

    /** Roles that participate in the client approval chain, in order. */
    public static function chain(): array
    {
        return [self::Marketing, self::Planificacion, self::Comercial];
    }

    /** Only commercial users are restricted to sectors. */
    public function usesSectors(): bool
    {
        return $this === self::Comercial;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
