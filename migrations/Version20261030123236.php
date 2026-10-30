<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030123236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Withdrawal for the electronic withdrawal function (§ 356a BGB)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE withdrawal (id INT AUTO_INCREMENT NOT NULL, invoice_id INT DEFAULT NULL, user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, contract_reference VARCHAR(255) NOT NULL, received_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) NOT NULL, admin_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_6D2D3B452989F1FD (invoice_id), INDEX IDX_6D2D3B45A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE withdrawal ADD CONSTRAINT FK_6D2D3B452989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE withdrawal ADD CONSTRAINT FK_6D2D3B45A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE withdrawal DROP FOREIGN KEY FK_6D2D3B452989F1FD');
        $this->addSql('ALTER TABLE withdrawal DROP FOREIGN KEY FK_6D2D3B45A76ED395');
        $this->addSql('DROP TABLE withdrawal');
    }
}
