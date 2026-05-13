<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne user.default_break_minutes (pause par défaut en minutes,
 * configurable depuis le profil et utilisée pour pré-remplir la saisie).
 */
final class Version20260513153658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.default_break_minutes (NOT NULL DEFAULT 60)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD default_break_minutes INT DEFAULT 60 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP default_break_minutes');
    }
}
