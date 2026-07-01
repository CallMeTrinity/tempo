<?php

namespace App\Export;

/**
 * Cumul d'heures sur un projet pour la période exportée.
 * Alimente l'onglet « Projets » (xlsx) et le pied de fichier CSV.
 * `colorHex` reprend la couleur du projet (pastille dans le xlsx).
 */
final readonly class ProjectTotal
{
    public function __construct(
        public string $name,
        public string $scopeLabel,
        public float $hours,
        public int $days,
        public string $colorHex,
    ) {
    }
}
