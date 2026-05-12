<?php

namespace App\Enum;

enum ContractType: string
{
    case CDI = 'cdi';
    case CDD = 'cdd';
    case FREELANCE = 'freelance';
    case INTERNSHIP = 'internship';
    case APPRENTICESHIP = 'apprenticeship';

    public function getLabel(): string
    {
        return match ($this) {
            self::CDI => 'CDI',
            self::CDD => 'CDD',
            self::FREELANCE => 'Freelance',
            self::INTERNSHIP => 'Stage',
            self::APPRENTICESHIP => 'Alternance',
        };
    }
}
