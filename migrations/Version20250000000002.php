<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\VoucherType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250000000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO voucher_type (units, validity_months, unitary_value, name, is_active, created_at, updated_at) 
                            VALUES (1, 6, 0, "'.VoucherType::NAME_REFUND.'", 1, NOW(), NOW())');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
