<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case Precio = 'precio';
    case Descripcion = 'descripcion';
    case Imagen = 'imagen';
    case Diseno = 'diseno';
    /* Cencosud's own vocabulary, added 24 Sep 2026. */
    case Dinamica = 'dinamica';
    case IngresaDinamica = 'ingresa_dinamica';
    case Compaginada = 'compaginada';
    case Pata = 'pata';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Precio => 'Precio',
            self::Descripcion => 'Descripción',
            self::Imagen => 'Imagen',
            self::Diseno => 'Ajustes de diseño',
            self::Dinamica => 'Dinámica',
            self::IngresaDinamica => 'Ingresa Dinámica',
            self::Compaginada => 'Compaginada',
            self::Pata => 'Pata',
            self::Otro => 'Otro',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Precio => 'precio',
            self::Descripcion => 'descripción',
            self::Imagen => 'imagen',
            self::Diseno => 'diseño',
            self::Dinamica => 'dinámica',
            self::IngresaDinamica => 'ingresa dinámica',
            self::Compaginada => 'compaginada',
            self::Pata => 'pata',
            self::Otro => 'otro',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
