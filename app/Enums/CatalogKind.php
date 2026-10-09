<?php

namespace App\Enums;

/** What Ogilvy delivers: the printed piece the catalog corresponds to. */
enum CatalogKind: string
{
    case Octavilla = 'octavilla';
    case Cuartilla = 'cuartilla';
    case Volante = 'volante';

    public function label(): string
    {
        return match ($this) {
            self::Octavilla => 'Octavilla',
            self::Cuartilla => 'Cuartilla',
            self::Volante => 'Volante',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
