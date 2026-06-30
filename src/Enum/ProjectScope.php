<?php

namespace App\Enum;

enum ProjectScope: string
{
    case TEAM = 'team';
    case PERSONAL = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEAM => 'Équipe',
            self::PERSONAL => 'Personnel',
        };
    }

}
