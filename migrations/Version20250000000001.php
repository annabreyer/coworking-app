<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250000000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO `user` (first_name, last_name, birthdate, email, mobile_phone, roles, password, is_active, is_verified, created_at, updated_at) 
                            VALUES ("Anna", "Breyer", "1981-05-12", "office@coworking-hahnheim.de", "+491746509125",'.  json_encode("[\"ROLE_SUPER_ADMIN\"]") .', "test", "1", "1", NOW(), NOW())');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
