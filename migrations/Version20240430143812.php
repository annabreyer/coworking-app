<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240430143812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9946D802');
        $this->addSql('DROP INDEX IDX_6D28840D9946D802 ON payment');
        $this->addSql('ALTER TABLE payment CHANGE paypal_order_id paypal_order_id VARCHAR(255) DEFAULT NULL, CHANGE amount amount INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment CHANGE amount amount INT NOT NULL, CHANGE paypal_order_id paypal_order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9946D802 FOREIGN KEY (paypal_order_id) REFERENCES paypal_order (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_6D28840D9946D802 ON payment (paypal_order_id)');
    }
}
