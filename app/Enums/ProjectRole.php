<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return $this === self::Manager ? 'Moderador' : 'Miembro';
    }
}
