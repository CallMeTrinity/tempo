<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne la BDD sur la roadmap :
 *   - time_entry.day_type devient NOT NULL avec défaut 'worked' (les lignes
 *     existantes nulles sont rétro-comblées en 'worked').
 *   - user.default_remote_days devient NOT NULL avec défaut '[]' (idem).
 */
final class Version20260513150342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce NOT NULL constraints on TimeEntry.dayType and User.defaultRemoteDays';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE time_entry SET day_type = 'worked' WHERE day_type IS NULL");
        $this->addSql("ALTER TABLE time_entry CHANGE day_type day_type VARCHAR(255) NOT NULL");

        $this->addSql("UPDATE user SET default_remote_days = '[]' WHERE default_remote_days IS NULL");
        $this->addSql("ALTER TABLE user CHANGE default_remote_days default_remote_days JSON NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE time_entry CHANGE day_type day_type VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE user CHANGE default_remote_days default_remote_days JSON DEFAULT NULL");
    }
}
