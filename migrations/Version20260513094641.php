<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513094641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_entry ADD day_type VARCHAR(255) DEFAULT NULL, CHANGE start_time start_time TIME DEFAULT NULL, CHANGE end_time end_time TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD working_days_per_week INT DEFAULT 5 NOT NULL, ADD default_remote_days JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_entry DROP day_type, CHANGE start_time start_time TIME NOT NULL, CHANGE end_time end_time TIME NOT NULL');
        $this->addSql('ALTER TABLE user DROP working_days_per_week, DROP default_remote_days');
    }
}
