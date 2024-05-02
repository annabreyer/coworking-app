<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240429162805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paypal_order ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE payment ADD paypal_order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9946D802 FOREIGN KEY (paypal_order_id) REFERENCES paypal_order (id)');
        $this->addSql('CREATE INDEX IDX_6D28840D9946D802 ON payment (paypal_order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paypal_order DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9946D802');
        $this->addSql('DROP INDEX IDX_6D28840D9946D802 ON payment');
        $this->addSql('ALTER TABLE payment DROP paypal_order_id');
    }
}
