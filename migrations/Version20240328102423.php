<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240328102423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_action (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, admin_user_id INT DEFAULT NULL, request_uri VARCHAR(255) DEFAULT NULL, data JSON NOT NULL, method VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1A04BD6BA76ED395 (user_id), INDEX IDX_1A04BD6B6352511C (admin_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_action ADD CONSTRAINT FK_1A04BD6BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE admin_action ADD CONSTRAINT FK_1A04BD6B6352511C FOREIGN KEY (admin_user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_action ADD method VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_action DROP FOREIGN KEY FK_1A04BD6BA76ED395');
        $this->addSql('ALTER TABLE admin_action DROP FOREIGN KEY FK_1A04BD6B6352511C');
        $this->addSql('DROP TABLE admin_action');
        $this->addSql('ALTER TABLE user_action DROP method');
    }
}
