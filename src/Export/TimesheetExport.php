<?php

namespace App\Export;

/**
 * Données d'un export, indépendantes du format de sortie.
 * Construites une fois par {@see TimeEntryExporter}, puis rendues par un writer
 * (CSV ou xlsx). Contient le détail jour par jour et le récap par projet.
 */
final readonly class TimesheetExport
{
    /**
     * En-têtes des colonnes du détail (une par jour).
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'Date',
        'Jour',
        'Type de jour',
        'Début',
        'Fin',
        'Pause (min)',
        'Heures',
        'Projets',
        'Statut',
        'Note',
    ];

    /**
     * @param list<TimesheetRow>  $rows
     * @param list<ProjectTotal>  $projectTotals
     */
    public function __construct(
        public string $userLabel,
        public \DateTimeInterface $from,
        public \DateTimeInterface $to,
        public array $rows,
        public float $totalHours,
        public array $projectTotals,
    ) {
    }

    public function hasProjects(): bool
    {
        return $this->projectTotals !== [];
    }

    /**
     * Vrai si les données couvrent plus d'une année ISO : on ajoute alors un
     * niveau de regroupement « année » dans le détail xlsx.
     */
    public function spansMultipleYears(): bool
    {
        $years = [];
        foreach ($this->rows as $row) {
            $years[$row->isoYear] = true;
        }

        return count($years) > 1;
    }

    public function totalAllocatedHours(): float
    {
        return array_sum(array_map(static fn (ProjectTotal $p): float => $p->hours, $this->projectTotals));
    }

    /**
     * Base du nom de fichier (sans extension), ex. « tempo_2026-07-01_2026-07-31 ».
     */
    public function filenameBase(): string
    {
        return sprintf('tempo_%s_%s', $this->from->format('Y-m-d'), $this->to->format('Y-m-d'));
    }

    public function periodLabel(): string
    {
        return sprintf('%s au %s', $this->from->format('d/m/Y'), $this->to->format('d/m/Y'));
    }
}
