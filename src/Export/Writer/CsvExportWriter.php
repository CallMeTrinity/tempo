<?php

namespace App\Export\Writer;

use App\Export\TimesheetExport;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writer CSV : dump à plat, une ligne par jour. BOM UTF-8 + séparateur « ; »
 * pour que les accents et les colonnes s'ouvrent correctement dans Excel FR.
 * Pas de mise en forme ni d'onglets (limite du format) : le récap projets est
 * ajouté en pied de fichier plutôt que sur une feuille dédiée.
 */
final class CsvExportWriter
{
    private const BOM = "\xEF\xBB\xBF";
    private const DELIMITER = ';';

    public function write(TimesheetExport $export): Response
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, self::BOM);

        $this->put($stream, TimesheetExport::COLUMNS);
        foreach ($export->rows as $row) {
            $this->put($stream, [
                $row->date,
                $row->weekday,
                $row->dayTypeLabel,
                $row->start ?? '',
                $row->end ?? '',
                $row->breakMinutes !== null ? (string) $row->breakMinutes : '',
                $this->number($row->hoursWorked),
                $row->projectsText,
                $row->statusLabel,
                $row->note,
            ]);
        }
        $this->put($stream, ['Total', '', '', '', '', '', $this->number($export->totalHours), '', '', '']);

        if ($export->hasProjects()) {
            $this->put($stream, []);
            $this->put($stream, ['Récapitulatif par projet']);
            $this->put($stream, ['Projet', 'Type', 'Heures', 'Jours']);
            foreach ($export->projectTotals as $project) {
                $this->put($stream, [
                    $project->name,
                    $project->scopeLabel,
                    $this->number($project->hours),
                    (string) $project->days,
                ]);
            }
            $this->put($stream, ['Total', '', $this->number($export->totalAllocatedHours()), '']);
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $this->response($content, $export->filenameBase() . '.csv');
    }

    /**
     * @param list<string> $fields
     */
    private function put($stream, array $fields): void
    {
        fputcsv($stream, $fields, self::DELIMITER, '"', '', "\r\n");
    }

    /**
     * Décimal à la française (virgule), Excel FR le lit comme un nombre.
     */
    private function number(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    private function response(string $content, string $filename): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
