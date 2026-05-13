<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table blacklisted_email : empêche la création de comptes avec un email
 * refusé par un admin.
 */
final class Version20260513211758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blacklisted_email table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blacklisted_email (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, reason VARCHAR(255) DEFAULT NULL, blacklisted_at DATETIME NOT NULL, blacklisted_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_9F7CEB2BE7927C74 (email), INDEX IDX_9F7CEB2BD209B75B (blacklisted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blacklisted_email ADD CONSTRAINT FK_9F7CEB2BD209B75B FOREIGN KEY (blacklisted_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blacklisted_email DROP FOREIGN KEY FK_9F7CEB2BD209B75B');
        $this->addSql('DROP TABLE blacklisted_email');
    }
}
