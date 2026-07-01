<?php

namespace App\Tests\Export;

use App\Export\ProjectTotal;
use App\Export\TimeEntryExporter;
use App\Export\TimesheetExport;
use App\Export\TimesheetRow;
use App\Export\Writer\CsvExportWriter;
use App\Export\Writer\XlsxExportWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PHPUnit\Framework\TestCase;

/**
 * Couvre le rendu des deux formats d'export (issues #9 et #10) : colonnes
 * partagées, regroupement année/semaine et totaux du xlsx, colonne projets par
 * jour, et onglet récap projets. On construit un TimesheetExport en mémoire.
 */
class ExportWriterTest extends TestCase
{
    private function row(string $date, string $dayTypeValue, string $dayTypeLabel, float $hours, string $projects = '', string $note = ''): TimesheetRow
    {
        $d = new \DateTimeImmutable($date);

        return new TimesheetRow(
            date: $d->format('Y-m-d'),
            weekday: $d->format('l'),
            dayTypeValue: $dayTypeValue,
            dayTypeLabel: $dayTypeLabel,
            start: $dayTypeValue === 'worked' ? '09:00' : null,
            end: $dayTypeValue === 'worked' ? '17:00' : null,
            breakMinutes: $dayTypeValue === 'worked' ? 60 : null,
            hoursWorked: $hours,
            projectsText: $projects,
            statusLabel: 'Approuvé',
            note: $note,
            isoYear: (int) $d->format('o'),
            isoWeek: (int) $d->format('W'),
        );
    }

    private function sampleExport(): TimesheetExport
    {
        return new TimesheetExport(
            userLabel: 'Antonin Pamart',
            from: new \DateTimeImmutable('2026-07-01'),
            to: new \DateTimeImmutable('2026-07-31'),
            rows: [
                $this->row('2026-07-01', 'worked', 'Bureau', 7.0, 'Alpha: 4 h | Bêta: 2,5 h', 'Réunion café'),
                $this->row('2026-07-02', 'remote', 'Télétravail', 7.0),
            ],
            totalHours: 14.0,
            projectTotals: [
                new ProjectTotal('Alpha', 'Équipe', 4.0, 1, '#3b6fb5'),
                new ProjectTotal('Bêta', 'Personnel', 2.5, 1, '#7c5cc4'),
            ],
        );
    }

    private function loadXlsx(TimesheetExport $export): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $file = tempnam(sys_get_temp_dir(), 'tempo_xlsx_') . '.xlsx';
        file_put_contents($file, (string) (new XlsxExportWriter())->write($export)->getContent());
        try {
            return (new XlsxReader())->load($file);
        } finally {
            @unlink($file);
        }
    }

    public function testCsvHasBomDelimiterHeaderAndTotals(): void
    {
        $response = (new CsvExportWriter())->write($this->sampleExport());
        $content = (string) $response->getContent();

        self::assertStringStartsWith("\xEF\xBB\xBF", $content, 'BOM UTF-8 attendu pour Excel FR');
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        self::assertStringContainsString('Date;Jour;"Type de jour"', $content);
        self::assertStringContainsString('Réunion café', $content);
        self::assertStringContainsString('Alpha: 4 h | Bêta: 2,5 h', $content);
        self::assertStringContainsString('Total;;;;;;14,00', $content);
        self::assertStringContainsString('Récapitulatif par projet', $content);
        self::assertStringContainsString('Alpha;Équipe;4,00;1', $content);
    }

    public function testXlsxGroupsByWeekWithWeeklyTotal(): void
    {
        $response = (new XlsxExportWriter())->write($this->sampleExport());
        self::assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));

        $spreadsheet = $this->loadXlsx($this->sampleExport());
        self::assertSame(['Détail', 'Projets'], $spreadsheet->getSheetNames());

        $detail = $spreadsheet->getSheetByName('Détail');
        self::assertSame('Date', $detail->getCell('A1')->getValue());
        self::assertSame('Projets', $detail->getCell('H1')->getValue());

        // Bandeau semaine (row 2), puis 2 jours, total semaine, total général.
        self::assertStringContainsString('Semaine', (string) $detail->getCell('A2')->getValue());
        self::assertSame(7.0, (float) $detail->getCell('G3')->getValue());
        self::assertSame('Alpha: 4 h | Bêta: 2,5 h', $detail->getCell('H3')->getValue());
        self::assertStringContainsString('Total semaine', (string) $detail->getCell('A5')->getValue());
        self::assertSame(14.0, (float) $detail->getCell('G5')->getValue());
        self::assertSame('Total général', $detail->getCell('A6')->getValue());
        self::assertSame(14.0, (float) $detail->getCell('G6')->getValue());

        $projects = $spreadsheet->getSheetByName('Projets');
        self::assertSame('Alpha', $projects->getCell('A2')->getValue());
        self::assertSame(4.0, (float) $projects->getCell('C2')->getValue());
        self::assertSame(6.5, (float) $projects->getCell('C4')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function testXlsxAddsYearSectionsWhenSpanningMultipleYears(): void
    {
        $export = new TimesheetExport(
            userLabel: 'A',
            from: new \DateTimeImmutable('2025-06-15'),
            to: new \DateTimeImmutable('2026-06-15'),
            rows: [
                $this->row('2025-06-16', 'worked', 'Bureau', 7.0),
                $this->row('2026-06-15', 'worked', 'Bureau', 7.0),
            ],
            totalHours: 14.0,
            projectTotals: [],
        );

        $spreadsheet = $this->loadXlsx($export);
        $detail = $spreadsheet->getSheetByName('Détail');

        $colA = [];
        foreach ($detail->getRowIterator() as $r) {
            $colA[] = (string) $detail->getCell('A' . $r->getRowIndex())->getValue();
        }
        $text = implode("\n", $colA);

        self::assertStringContainsString('Année 2025', $text);
        self::assertStringContainsString('Année 2026', $text);
        self::assertStringContainsString('Total 2025', $text);
        self::assertStringContainsString('Total général', $text);

        $spreadsheet->disconnectWorksheets();
    }

    public function testXlsxWithoutProjectsHasSingleSheet(): void
    {
        $base = $this->sampleExport();
        $export = new TimesheetExport(
            userLabel: $base->userLabel,
            from: $base->from,
            to: $base->to,
            rows: $base->rows,
            totalHours: $base->totalHours,
            projectTotals: [],
        );

        $spreadsheet = $this->loadXlsx($export);
        self::assertSame(['Détail'], $spreadsheet->getSheetNames());
        $spreadsheet->disconnectWorksheets();
    }

    public function testFormatHoursTrimsTrailingZeros(): void
    {
        self::assertSame('4', TimeEntryExporter::formatHours(4.0));
        self::assertSame('2,5', TimeEntryExporter::formatHours(2.5));
        self::assertSame('7,25', TimeEntryExporter::formatHours(7.25));
    }
}
