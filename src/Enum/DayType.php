<?php

namespace App\Enum;

enum DayType : String
{

    case REMOTE = 'remote';
    case WORKED = 'worked';
    case PTO = 'pto';
    case OFF = 'off';

    public function getLabel() : String
    {
        return match($this) {
            self::REMOTE => 'Télétravail',
            self::WORKED => 'Travaillé',
            self::PTO => 'Congé payé',
            self::OFF => 'Congé non payé',
        };
    }
}
