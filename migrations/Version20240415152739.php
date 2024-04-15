<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240415152739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE voucher_type (id INT AUTO_INCREMENT NOT NULL, units INT NOT NULL, validity_months INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE price ADD voucher_type_id INT DEFAULT NULL, ADD is_unitary TINYINT(1) NOT NULL, ADD is_voucher TINYINT(1) NOT NULL, ADD is_subscription TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE price ADD CONSTRAINT FK_CAC822D9681A694 FOREIGN KEY (voucher_type_id) REFERENCES voucher_type (id)');
        $this->addSql('CREATE INDEX IDX_CAC822D9681A694 ON price (voucher_type_id)');
        $this->addSql('ALTER TABLE voucher ADD voucher_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voucher ADD CONSTRAINT FK_1392A5D8681A694 FOREIGN KEY (voucher_type_id) REFERENCES voucher_type (id)');
        $this->addSql('CREATE INDEX IDX_1392A5D8681A694 ON voucher (voucher_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE price DROP FOREIGN KEY FK_CAC822D9681A694');
        $this->addSql('ALTER TABLE voucher DROP FOREIGN KEY FK_1392A5D8681A694');
        $this->addSql('DROP TABLE voucher_type');
        $this->addSql('DROP INDEX IDX_CAC822D9681A694 ON price');
        $this->addSql('ALTER TABLE price DROP voucher_type_id, DROP is_unitary, DROP is_voucher, DROP is_subscription');
        $this->addSql('DROP INDEX IDX_1392A5D8681A694 ON voucher');
        $this->addSql('ALTER TABLE voucher DROP voucher_type_id');
    }
}
