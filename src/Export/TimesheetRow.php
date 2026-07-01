<?php

namespace App\Export;

/**
 * Une ligne de détail de l'export : une journée saisie.
 * Les valeurs sont typées (pas encore formatées) pour que chaque writer
 * décide de son rendu (nombre Excel sommable vs texte CSV).
 * `isoYear`/`isoWeek` servent au regroupement année/semaine du rendu xlsx.
 */
final readonly class TimesheetRow
{
    public function __construct(
        public string $date,
        public string $weekday,
        public string $dayTypeValue,
        public string $dayTypeLabel,
        public ?string $start,
        public ?string $end,
        public ?int $breakMinutes,
        public float $hoursWorked,
        public string $projectsText,
        public string $statusLabel,
        public string $note,
        public int $isoYear,
        public int $isoWeek,
    ) {
    }
}
