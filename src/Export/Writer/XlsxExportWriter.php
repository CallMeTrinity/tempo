<?php

namespace App\Export\Writer;

use App\Export\TimesheetExport;
use App\Export\TimesheetRow;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writer xlsx : le format « riche ». Détail regroupé par année (si l'export
 * couvre plus d'un an) puis par semaine, avec numéro de semaine et total
 * hebdomadaire, lignes colorées par type de jour, et un onglet « Projets »
 * récapitulant les heures par projet (avec pastille de couleur).
 */
final class XlsxExportWriter
{
    private const HEADER_FILL = 'FF1F2937';   // gray-800
    private const HEADER_FONT = 'FFFFFFFF';
    private const YEAR_FILL = 'FF334155';     // slate-700
    private const WEEK_FILL = 'FFE0E7FF';     // indigo-100
    private const WEEK_TOTAL_FILL = 'FFEEF2FF'; // indigo-50
    private const GRAND_TOTAL_FILL = 'FFD9E0F5';
    private const HOURS_FORMAT = '0.00';

    /**
     * Fonds pastel par type de jour (ARGB).
     */
    private const DAY_FILLS = [
        'worked' => 'FFEFF4FB',
        'remote' => 'FFF1EDFB',
        'pto' => 'FFEAF6EF',
        'uto' => 'FFFBF3E2',
        'off' => 'FFF3F4F6',
    ];

    private const LAST_COLUMN = 'J'; // 10 colonnes de détail (A..J)

    public function write(TimesheetExport $export): Response
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Tempo')
            ->setTitle('Export ' . $export->periodLabel())
            ->setDescription(sprintf('%s — %s', $export->userLabel, $export->periodLabel()));

        $this->buildDetailSheet($spreadsheet->getActiveSheet(), $export);

        if ($export->hasProjects()) {
            $this->buildProjectSheet($spreadsheet->createSheet(), $export);
        }

        $spreadsheet->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $this->response($content, $export->filenameBase() . '.xlsx');
    }

    private function buildDetailSheet(Worksheet $sheet, TimesheetExport $export): void
    {
        $sheet->setTitle('Détail');
        $columns = TimesheetExport::COLUMNS;

        $sheet->fromArray($columns, null, 'A1');
        $this->styleHeader($sheet, 'A1', self::LAST_COLUMN . '1');
        $sheet->freezePane('A2');

        $withYears = $export->spansMultipleYears();
        $row = 2;
        $firstDataRow = 2;

        foreach ($this->groupByYearAndWeek($export->rows) as $year => $weeks) {
            if ($withYears) {
                $this->banner($sheet, $row, sprintf('Année %d', $year), self::YEAR_FILL, self::HEADER_FONT);
                ++$row;
            }

            $yearHours = 0.0;
            foreach ($weeks as $weekRows) {
                /** @var list<TimesheetRow> $weekRows */
                $first = $weekRows[0];
                $this->banner($sheet, $row, $this->weekLabel($first), self::WEEK_FILL, 'FF1F2937');
                ++$row;

                $weekHours = 0.0;
                foreach ($weekRows as $entry) {
                    $this->writeDayRow($sheet, $row, $entry);
                    $weekHours += $entry->hoursWorked;
                    ++$row;
                }

                $this->totalRow($sheet, $row, sprintf('Total semaine %02d', $first->isoWeek), $weekHours, self::WEEK_TOTAL_FILL);
                ++$row;
                $yearHours += $weekHours;
            }

            if ($withYears) {
                $this->totalRow($sheet, $row, sprintf('Total %d', $year), $yearHours, self::GRAND_TOTAL_FILL);
                ++$row;
            }
        }

        $this->totalRow($sheet, $row, 'Total général', $export->totalHours, self::GRAND_TOTAL_FILL);
        $lastRow = $row;

        $sheet->getStyle("G{$firstDataRow}:G{$lastRow}")->getNumberFormat()->setFormatCode(self::HOURS_FORMAT);
        $sheet->getStyle("A1:" . self::LAST_COLUMN . $lastRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $this->autosize($sheet, $columns);
        // Colonnes texte : largeur fixe plutôt qu'auto (sinon elles s'étirent).
        $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(32);
        $sheet->getColumnDimension('J')->setAutoSize(false)->setWidth(32);
    }

    private function writeDayRow(Worksheet $sheet, int $row, TimesheetRow $entry): void
    {
        $sheet->setCellValue("A$row", $entry->date);
        $sheet->setCellValue("B$row", $entry->weekday);
        $sheet->setCellValue("C$row", $entry->dayTypeLabel);
        $sheet->setCellValue("D$row", $entry->start ?? '');
        $sheet->setCellValue("E$row", $entry->end ?? '');
        if ($entry->breakMinutes !== null) {
            $sheet->setCellValue("F$row", $entry->breakMinutes);
        }
        $sheet->setCellValue("G$row", $entry->hoursWorked);
        $sheet->setCellValueExplicit("H$row", $entry->projectsText, DataType::TYPE_STRING);
        $sheet->setCellValue("I$row", $entry->statusLabel);
        $sheet->setCellValueExplicit("J$row", $entry->note, DataType::TYPE_STRING);

        $fill = self::DAY_FILLS[$entry->dayTypeValue] ?? null;
        if ($fill !== null) {
            $sheet->getStyle("A$row:" . self::LAST_COLUMN . $row)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        }
    }

    private function buildProjectSheet(Worksheet $sheet, TimesheetExport $export): void
    {
        $sheet->setTitle('Projets');
        $columns = ['Projet', 'Type', 'Heures', 'Jours'];

        $sheet->fromArray($columns, null, 'A1');
        $this->styleHeader($sheet, 'A1', 'D1');

        $row = 2;
        foreach ($export->projectTotals as $project) {
            $sheet->setCellValue("A$row", $project->name);
            $sheet->setCellValue("B$row", $project->scopeLabel);
            $sheet->setCellValue("C$row", $project->hours);
            $sheet->setCellValue("D$row", $project->days);

            // Pastille : le nom du projet prend un fond teinté de sa couleur,
            // avec une bordure gauche épaisse dans la teinte pleine.
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($this->tint($project->colorHex, 0.85));
            $sheet->getStyle("A$row")->getBorders()->getLeft()
                ->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($this->argb($project->colorHex));
            ++$row;
        }

        $this->totalRow($sheet, $row, 'Total', $export->totalAllocatedHours(), self::GRAND_TOTAL_FILL, 'C');

        $sheet->getStyle("C2:C$row")->getNumberFormat()->setFormatCode(self::HOURS_FORMAT);
        $sheet->getStyle("A1:D$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $this->autosize($sheet, $columns);
    }

    /**
     * Bandeau fusionné sur toute la largeur (année ou semaine).
     */
    private function banner(Worksheet $sheet, int $row, string $label, string $fillArgb, string $fontArgb): void
    {
        $range = "A$row:" . self::LAST_COLUMN . $row;
        $sheet->mergeCells($range);
        $sheet->setCellValue("A$row", $label);
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB($fontArgb);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillArgb);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    /**
     * Ligne de total : libellé en colonne A, valeur d'heures dans `$hoursCol`.
     */
    private function totalRow(Worksheet $sheet, int $row, string $label, float $hours, string $fillArgb, string $hoursCol = 'G'): void
    {
        $sheet->setCellValue("A$row", $label);
        $sheet->setCellValue("$hoursCol$row", round($hours, 2));
        $lastCol = $hoursCol === 'G' ? self::LAST_COLUMN : 'D';
        $style = $sheet->getStyle("A$row:$lastCol$row");
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillArgb);
    }

    private function styleHeader(Worksheet $sheet, string $from, string $to): void
    {
        $style = $sheet->getStyle("$from:$to");
        $style->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(20);
    }

    private function weekLabel(TimesheetRow $first): string
    {
        $monday = (new \DateTime())->setISODate($first->isoYear, $first->isoWeek);
        $sunday = (clone $monday)->modify('+6 days');

        return sprintf(
            'Semaine %02d · %s → %s',
            $first->isoWeek,
            $monday->format('d/m'),
            $sunday->format('d/m'),
        );
    }

    /**
     * Regroupe les lignes (déjà triées par date) par année ISO puis semaine ISO.
     *
     * @param list<TimesheetRow> $rows
     *
     * @return array<int, array<int, list<TimesheetRow>>>
     */
    private function groupByYearAndWeek(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $entry) {
            $grouped[$entry->isoYear][$entry->isoWeek][] = $entry;
        }

        return $grouped;
    }

    /**
     * @param list<string> $columns
     */
    private function autosize(Worksheet $sheet, array $columns): void
    {
        foreach (array_keys($columns) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setAutoSize(true);
        }
    }

    /**
     * `#rrggbb` → `AARRGGBB` opaque.
     */
    private function argb(string $hex): string
    {
        return 'FF' . strtoupper(ltrim($hex, '#'));
    }

    /**
     * Teinte claire : mélange la couleur avec du blanc (`$whiteRatio` ∈ [0,1]).
     */
    private function tint(string $hex, float $whiteRatio): string
    {
        $hex = ltrim($hex, '#');
        $mix = static function (int $channel) use ($whiteRatio): int {
            return (int) round($channel + (255 - $channel) * $whiteRatio);
        };

        return sprintf(
            'FF%02X%02X%02X',
            $mix((int) hexdec(substr($hex, 0, 2))),
            $mix((int) hexdec(substr($hex, 2, 2))),
            $mix((int) hexdec(substr($hex, 4, 2))),
        );
    }

    private function response(string $content, string $filename): Response
    {
        $response = new Response($content);
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
