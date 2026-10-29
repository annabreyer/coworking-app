<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918151631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add BookingType (Ganztag/Halbtag) and link it to Booking, Price and VoucherType';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO booking_type (name, is_active, created_at, updated_at) VALUES ('Ganztag', 1, NOW(), NOW())");
        $this->addSql("INSERT INTO booking_type (name, is_active, created_at, updated_at) VALUES ('Halbtag', 1, NOW(), NOW())");

        $this->addSql('ALTER TABLE booking ADD type_id INT DEFAULT NULL');
        $this->addSql("UPDATE booking SET type_id = (SELECT id FROM booking_type WHERE name = 'Ganztag' LIMIT 1)");
        $this->addSql('ALTER TABLE booking MODIFY type_id INT NOT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEC54C8C93 FOREIGN KEY (type_id) REFERENCES booking_type (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEC54C8C93 ON booking (type_id)');

        $this->addSql('ALTER TABLE price ADD booking_type_id INT DEFAULT NULL');
        $this->addSql("UPDATE price SET booking_type_id = (SELECT id FROM booking_type WHERE name = 'Ganztag' LIMIT 1)");
        $this->addSql('ALTER TABLE price MODIFY booking_type_id INT NOT NULL');
        $this->addSql('ALTER TABLE price ADD CONSTRAINT FK_CAC822D99FF2C9B3 FOREIGN KEY (booking_type_id) REFERENCES booking_type (id)');
        $this->addSql('CREATE INDEX IDX_CAC822D99FF2C9B3 ON price (booking_type_id)');

        $this->addSql('ALTER TABLE voucher_type ADD booking_type_id INT DEFAULT NULL');
        $this->addSql("UPDATE voucher_type SET booking_type_id = (SELECT id FROM booking_type WHERE name = 'Ganztag' LIMIT 1) WHERE name IS NULL OR name <> 'refund'");
        $this->addSql('ALTER TABLE voucher_type ADD CONSTRAINT FK_8DB3D7919FF2C9B3 FOREIGN KEY (booking_type_id) REFERENCES booking_type (id)');
        $this->addSql('CREATE INDEX IDX_8DB3D7919FF2C9B3 ON voucher_type (booking_type_id)');

        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEC54C8C93');
        $this->addSql('ALTER TABLE price DROP FOREIGN KEY FK_CAC822D99FF2C9B3');
        $this->addSql('ALTER TABLE voucher_type DROP FOREIGN KEY FK_8DB3D7919FF2C9B3');
        $this->addSql('DROP TABLE booking_type');
        $this->addSql('DROP INDEX IDX_E00CEDDEC54C8C93 ON booking');
        $this->addSql('ALTER TABLE booking DROP type_id');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('DROP INDEX IDX_CAC822D99FF2C9B3 ON price');
        $this->addSql('ALTER TABLE price DROP booking_type_id');
        $this->addSql('DROP INDEX IDX_8DB3D7919FF2C9B3 ON voucher_type');
        $this->addSql('ALTER TABLE voucher_type DROP booking_type_id');
    }
}
