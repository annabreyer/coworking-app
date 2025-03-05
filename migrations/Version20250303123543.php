<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250303123543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_type (id INT AUTO_INCREMENT NOT NULL, price_id INT NOT NULL, name VARCHAR(100) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_944F7567D614C7E7 (price_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking_type ADD CONSTRAINT FK_944F7567D614C7E7 FOREIGN KEY (price_id) REFERENCES price (id)');
        $this->addSql('ALTER TABLE booking ADD type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEC54C8C93 FOREIGN KEY (type_id) REFERENCES booking_type (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEC54C8C93 ON booking (type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEC54C8C93');
        $this->addSql('ALTER TABLE booking_type DROP FOREIGN KEY FK_944F7567D614C7E7');
        $this->addSql('DROP TABLE booking_type');
        $this->addSql('DROP INDEX IDX_E00CEDDEC54C8C93 ON booking');
        $this->addSql('ALTER TABLE booking DROP type_id');
    }
}
