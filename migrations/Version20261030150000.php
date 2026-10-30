<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the express request to start the booking before the end of the withdrawal period (§ 356 Abs. 4 BGB)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ADD early_performance_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP early_performance_requested_at');
    }
}
