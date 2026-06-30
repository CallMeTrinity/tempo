<?php

namespace App\Project;

/**
 * Source unique des couleurs de projet.
 *
 * Chaque clé est stockée telle quelle sur Project::$color. Le rendu (pastille,
 * badge) s'appuie sur la teinte `hex` exposée via la fonction Twig `constant()`
 * dans `admin/_project_color.html.twig`, ce qui évite de dupliquer la palette.
 */
final class ProjectColors
{
    /**
     * Clé de couleur => ['label' => libellé FR, 'hex' => teinte de base].
     *
     * La teinte sert de variable CSS (--pc) ; les fonds/bordures adoucis sont
     * dérivés via color-mix() côté CSS (cf. components/project.css).
     *
     * @var array<string, array{label: string, hex: string}>
     */
    public const COLORS = [
        'slate' => ['label' => 'Ardoise', 'hex' => '#64748b'],
        'blue' => ['label' => 'Bleu', 'hex' => '#3b6fb5'],
        'teal' => ['label' => 'Sarcelle', 'hex' => '#2f8f8f'],
        'emerald' => ['label' => 'Émeraude', 'hex' => '#2f8f6b'],
        'amber' => ['label' => 'Ambre', 'hex' => '#c08a2d'],
        'orange' => ['label' => 'Orange', 'hex' => '#c4663a'],
        'rose' => ['label' => 'Rose', 'hex' => '#c0476b'],
        'violet' => ['label' => 'Violet', 'hex' => '#7c5cc4'],
    ];

    public const DEFAULT = 'blue';

    public static function isValid(string $key): bool
    {
        return isset(self::COLORS[$key]);
    }

    /**
     * Retourne la clé si elle est connue, sinon la clé par défaut.
     */
    public static function normalize(?string $key): string
    {
        return $key !== null && self::isValid($key) ? $key : self::DEFAULT;
    }

    public static function hex(string $key): string
    {
        return self::COLORS[self::normalize($key)]['hex'];
    }
}
