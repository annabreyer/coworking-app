<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DevFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
            PriceFixtures::class,
            HalfDayPriceFixtures::class,
            VoucherFixtures::class,
            InvoiceFixtures::class,
            BookingWithInvoiceNoPaymentFixture::class,
            UpcomingBusinessDayFixtures::class,
            CoworkingRoomFixtures::class,
            UpcomingBookingFixtures::class,
            InvoiceBulkDownloadFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function load(ObjectManager $manager): void
    {
    }
}
