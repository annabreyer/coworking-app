<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240328124814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE room ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE mobile_phone mobile_phone VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE work_station ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE work_station DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE user CHANGE mobile_phone mobile_phone VARCHAR(35) DEFAULT NULL');
        $this->addSql('ALTER TABLE booking DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE room DROP created_at, DROP updated_at');
    }
}
