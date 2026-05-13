<?php

namespace App\Enum;

enum DayType: string
{
    case WORKED = 'worked';
    case REMOTE = 'remote';
    case PTO = 'pto';
    case UTO = 'uto';
    case OFF = 'off';

    public function getLabel(): string
    {
        return match ($this) {
            self::WORKED => 'Bureau',
            self::REMOTE => 'Télétravail',
            self::PTO => 'Congé payé',
            self::UTO => 'Congé non-payé',
            self::OFF => 'Absent',
        };
    }

    /**
     * Un jour qui compte comme une journée prestée (pour le décompte des jours saisis et le total d'heures).
     */
    public function isProductive(): bool
    {
        return match ($this) {
            self::WORKED, self::REMOTE, self::PTO, self::UTO => true,
            self::OFF => false,
        };
    }

    /**
     * Le jour nécessite la saisie d'horaires (start/end/break).
     */
    public function requiresTimes(): bool
    {
        return $this === self::WORKED;
    }
}
