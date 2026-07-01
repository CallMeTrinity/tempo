<?php

namespace App\Export;

/**
 * Formats d'export proposés à l'utilisateur.
 * - XLSX : format riche (mise en forme, onglet « Projets »), via PhpSpreadsheet.
 * - CSV  : dump à plat (BOM UTF-8 + séparateur « ; » pour Excel FR).
 */
enum ExportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::XLSX;
    }

    public function getExtension(): string
    {
        return $this->value;
    }

    public function getContentType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv; charset=UTF-8',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CSV => 'CSV',
            self::XLSX => 'Excel (.xlsx)',
        };
    }
}
