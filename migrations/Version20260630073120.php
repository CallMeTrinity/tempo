<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630073120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_user (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B4021E51166D1F9C (project_id), INDEX IDX_B4021E51A76ED395 (user_id), PRIMARY KEY (project_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_entry_project (id INT AUTO_INCREMENT NOT NULL, hours DOUBLE PRECISION NOT NULL, time_entry_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_8B842DF61EB30A8E (time_entry_id), INDEX IDX_8B842DF6166D1F9C (project_id), UNIQUE INDEX unique_time_entry_project (time_entry_id, project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entry_project ADD CONSTRAINT FK_8B842DF61EB30A8E FOREIGN KEY (time_entry_id) REFERENCES time_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entry_project ADD CONSTRAINT FK_8B842DF6166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE project ADD scope VARCHAR(255) NOT NULL, ADD icon VARCHAR(255) NOT NULL, ADD color VARCHAR(63) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE7E3C61F9 ON project (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51166D1F9C');
        $this->addSql('ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51A76ED395');
        $this->addSql('ALTER TABLE time_entry_project DROP FOREIGN KEY FK_8B842DF61EB30A8E');
        $this->addSql('ALTER TABLE time_entry_project DROP FOREIGN KEY FK_8B842DF6166D1F9C');
        $this->addSql('DROP TABLE project_user');
        $this->addSql('DROP TABLE time_entry_project');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE7E3C61F9');
        $this->addSql('DROP INDEX IDX_2FB3D0EE7E3C61F9 ON project');
        $this->addSql('ALTER TABLE project DROP scope, DROP icon, DROP color, DROP created_at, DROP updated_at, DROP owner_id');
    }
}
