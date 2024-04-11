<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240411104041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice CHANGE amount amount INT NOT NULL');
        $this->addSql('ALTER TABLE price CHANGE amount amount INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TYPE_ISACTIVE ON price (type, is_active)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice CHANGE amount amount VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_TYPE_ISACTIVE ON price');
        $this->addSql('ALTER TABLE price CHANGE amount amount VARCHAR(255) NOT NULL');
    }
}
