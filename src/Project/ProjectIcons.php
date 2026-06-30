<?php

namespace App\Project;

/**
 * Source unique des icônes de projet.
 *
 * Chaque clé est stockée telle quelle sur Project::$icon. Le rendu SVG inline
 * correspondant vit dans le partial Twig `admin/_project_icon.html.twig`
 * (mêmes clés). Ajouter une icône = ajouter une entrée ici ET son tracé SVG
 * dans le partial.
 */
final class ProjectIcons
{
    /**
     * Clé d'icône => libellé lisible (FR).
     *
     * @var array<string, string>
     */
    public const ICONS = [
        // Thématiques
        'folder' => 'Dossier',
        'code' => 'Développement',
        'zap' => 'Innovation',
        'chart' => 'Analytics',
        'droplet' => 'Design',
        'megaphone' => 'Communication',
        'wrench' => 'Maintenance',
        'users' => 'Équipe',
        'briefcase' => 'Mallette',
        'target' => 'Objectif',
        'lightbulb' => 'Idée',
        'package' => 'Colis',
        'globe' => 'Globe',
        'book' => 'Livre',
        'calendar' => 'Calendrier',
        'check' => 'Validé',
        'star' => 'Étoile',
        'heart' => 'Cœur',
        'flag' => 'Drapeau',
        'bookmark' => 'Marque-page',
        // Chiffres
        'num1' => '1',
        'num2' => '2',
        'num3' => '3',
        'num4' => '4',
        'num5' => '5',
        'num6' => '6',
        'num7' => '7',
        'num8' => '8',
        'num9' => '9',
    ];

    public const DEFAULT = 'folder';

    public static function isValid(string $key): bool
    {
        return isset(self::ICONS[$key]);
    }

    /**
     * Retourne la clé si elle est connue, sinon la clé par défaut.
     */
    public static function normalize(?string $key): string
    {
        return $key !== null && self::isValid($key) ? $key : self::DEFAULT;
    }
}
