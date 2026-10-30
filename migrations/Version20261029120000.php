<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional billing address to User and a billing address snapshot to Invoice';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD billing_name VARCHAR(255) DEFAULT NULL, ADD billing_street VARCHAR(255) DEFAULT NULL, ADD billing_post_code VARCHAR(10) DEFAULT NULL, ADD billing_city VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD billing_name VARCHAR(255) DEFAULT NULL, ADD billing_street VARCHAR(255) DEFAULT NULL, ADD billing_post_code VARCHAR(10) DEFAULT NULL, ADD billing_city VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP billing_name, DROP billing_street, DROP billing_post_code, DROP billing_city');
        $this->addSql('ALTER TABLE invoice DROP billing_name, DROP billing_street, DROP billing_post_code, DROP billing_city');
    }
}
