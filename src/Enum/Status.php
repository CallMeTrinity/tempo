<?php

namespace App\Enum;

enum Status: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case TO_BE_REVIEWED = 'to_be_reviewed';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SUBMITTED => 'À valider',
            self::APPROVED => 'Approuvé',
            self::TO_BE_REVIEWED => 'À revoir',
        };
    }

    /**
     * L'utilisateur peut éditer son entrée tant qu'elle n'est pas en attente
     * de validation ou approuvée.
     */
    public function isEditableByUser(): bool
    {
        return $this === self::DRAFT || $this === self::TO_BE_REVIEWED;
    }

    /**
     * L'utilisateur peut soumettre une entrée DRAFT ou TO_BE_REVIEWED pour
     * approbation par un admin.
     */
    public function canBeSubmittedByUser(): bool
    {
        return $this === self::DRAFT || $this === self::TO_BE_REVIEWED;
    }

    /**
     * L'utilisateur peut retirer sa soumission tant que l'admin n'a pas
     * approuvé. Une entrée APPROVED reste verrouillée.
     */
    public function canBeUnsubmittedByUser(): bool
    {
        return $this === self::SUBMITTED;
    }
}
