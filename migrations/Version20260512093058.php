<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512093058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_entry CHANGE break_duration break_duration INT DEFAULT NULL COMMENT \'Pause en minutes\'');
        $this->addSql('CREATE UNIQUE INDEX unique_user_date ON time_entry (user_id, date)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_user_date ON time_entry');
        $this->addSql('ALTER TABLE time_entry CHANGE break_duration break_duration INT DEFAULT NULL');
    }
}
