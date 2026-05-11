<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511134657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_entry (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, break_duration INT DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, status VARCHAR(255) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_6E537C0CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_6E537C0CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD password VARCHAR(255) NOT NULL, ADD google_id VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD role VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_6E537C0CA76ED395');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE time_entry');
        $this->addSql('ALTER TABLE user DROP password, DROP google_id, DROP created_at, DROP updated_at, DROP role');
    }
}
