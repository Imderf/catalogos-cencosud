<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Image = 'image';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Checkbox = 'checkbox';
    case Url = 'url';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto corto',
            self::Textarea => 'Descripción (texto largo)',
            self::Image => 'Imagen',
            self::Number => 'Número',
            self::Date => 'Fecha',
            self::Select => 'Selección única',
            self::MultiSelect => 'Selección múltiple',
            self::Checkbox => 'Casilla (sí/no)',
            self::Url => 'Enlace',
            self::User => 'Persona',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
