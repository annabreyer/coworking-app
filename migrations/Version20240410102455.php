<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240410102455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, amount INT NOT NULL, number VARCHAR(10) NOT NULL, date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_90651744A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, invoice_id INT DEFAULT NULL, voucher_id INT DEFAULT NULL, transaction_id INT DEFAULT NULL, amount INT NOT NULL, type VARCHAR(100) NOT NULL, date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_6D28840D2989F1FD (invoice_id), INDEX IDX_6D28840D28AA1B6F (voucher_id), INDEX IDX_6D28840D2FC0CB0F (transaction_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, amount INT NOT NULL, date DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE voucher (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, value INT NOT NULL, code VARCHAR(10) NOT NULL, expiry_date DATE NOT NULL, use_date DATE DEFAULT NULL, INDEX IDX_1392A5D8A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D28AA1B6F FOREIGN KEY (voucher_id) REFERENCES voucher (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE voucher ADD CONSTRAINT FK_1392A5D8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE2989F1FD ON booking (invoice_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE2989F1FD');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744A76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2989F1FD');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D28AA1B6F');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2FC0CB0F');
        $this->addSql('ALTER TABLE voucher DROP FOREIGN KEY FK_1392A5D8A76ED395');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE voucher');
        $this->addSql('DROP INDEX IDX_E00CEDDE2989F1FD ON booking');
        $this->addSql('ALTER TABLE booking DROP invoice_id');
    }
}
