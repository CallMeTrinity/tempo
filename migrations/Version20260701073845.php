<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701073845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.last_verification_email_sent_at (délai anti-spam avant renvoi de l\'email de confirmation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD last_verification_email_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP last_verification_email_sent_at');
    }
}
