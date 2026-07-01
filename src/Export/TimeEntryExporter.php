<?php

namespace App\Export;

use App\Entity\TimeEntry;
use App\Entity\TimeEntryProject;
use App\Entity\User;
use App\Export\Writer\CsvExportWriter;
use App\Export\Writer\XlsxExportWriter;
use App\Project\ProjectColors;
use App\Repository\TimeEntryProjectRepository;
use App\Repository\TimeEntryRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Construit les données d'export (détail jour par jour + récap projets) à
 * partir des entités, puis délègue le rendu au writer du format demandé.
 * La logique métier (colonnes, valeurs) est ici, une seule fois ; seul le
 * writer diffère entre CSV brut et xlsx formaté.
 */
final class TimeEntryExporter
{
    private const WEEKDAYS_FR = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public function __construct(
        private readonly TimeEntryRepository $entries,
        private readonly TimeEntryProjectRepository $allocations,
        private readonly CsvExportWriter $csvWriter,
        private readonly XlsxExportWriter $xlsxWriter,
    ) {
    }

    public function export(User $user, \DateTimeInterface $from, \DateTimeInterface $to, ExportFormat $format): Response
    {
        $data = $this->build($user, $from, $to);

        return match ($format) {
            ExportFormat::CSV => $this->csvWriter->write($data),
            ExportFormat::XLSX => $this->xlsxWriter->write($data),
        };
    }

    private function build(User $user, \DateTimeInterface $from, \DateTimeInterface $to): TimesheetExport
    {
        $entries = $this->entries->findByUserBetween(
            $user,
            \DateTime::createFromInterface($from),
            \DateTime::createFromInterface($to),
        );

        $rows = [];
        $totalHours = 0.0;
        foreach ($entries as $entry) {
            $hours = $entry->getHoursWorked();
            $totalHours += $hours;
            $date = $entry->getDate();
            $rows[] = new TimesheetRow(
                date: $date->format('Y-m-d'),
                weekday: self::WEEKDAYS_FR[(int) $date->format('N')] ?? '',
                dayTypeValue: $entry->getDayType()->value,
                dayTypeLabel: $entry->getDayType()->getLabel(),
                start: $entry->getStartTime()?->format('H:i'),
                end: $entry->getEndTime()?->format('H:i'),
                breakMinutes: $entry->getBreakDuration(),
                hoursWorked: $hours,
                projectsText: $this->formatProjects($entry),
                statusLabel: $entry->getStatus()?->getLabel() ?? '',
                note: (string) $entry->getNote(),
                isoYear: (int) $date->format('o'),
                isoWeek: (int) $date->format('W'),
            );
        }

        $projectTotals = [];
        foreach ($this->allocations->aggregateForUserBetween($user, $from, $to) as $row) {
            $projectTotals[] = new ProjectTotal(
                name: (string) $row['project']->getName(),
                scopeLabel: $row['project']->getScope()?->getLabel() ?? '',
                hours: $row['hours'],
                days: $row['days'],
                colorHex: ProjectColors::hex((string) $row['project']->getColor()),
            );
        }

        return new TimesheetExport(
            userLabel: $user->getFullName() ?? $user->getUserIdentifier(),
            from: $from,
            to: $to,
            rows: $rows,
            totalHours: round($totalHours, 2),
            projectTotals: $projectTotals,
        );
    }

    /**
     * Projets du jour et leurs heures, en une cellule texte lisible aussi bien
     * en CSV qu'en xlsx : « Projet A: 4 h | Projet B: 2,5 h ».
     */
    private function formatProjects(TimeEntry $entry): string
    {
        $parts = [];
        foreach ($entry->getProjectAllocations() as $allocation) {
            /** @var TimeEntryProject $allocation */
            $project = $allocation->getProject();
            if ($project === null) {
                continue;
            }
            $parts[$project->getName() ?? ''] = sprintf(
                '%s: %s h',
                $project->getName(),
                self::formatHours((float) $allocation->getHours()),
            );
        }

        ksort($parts);

        return implode(' | ', $parts);
    }

    /**
     * Heures décimales en libellé court FR : 4 → « 4 », 2.5 → « 2,5 ».
     */
    public static function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',');
    }
}
