<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BookingType;
use App\Entity\Price;
use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class HalfDayPriceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [PriceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $halfDay = $this->getReference(BookingTypeFixtures::HALF_DAY_REFERENCE, BookingType::class);

        $single = (new Price())
            ->setIsUnitary(true)
            ->setAmount(PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT)
            ->setIsActive(true)
            ->setBookingType($halfDay)
            ->setName('Einzelticket Halbtag')
        ;
        $manager->persist($single);
        $this->addReference('price-single-half-day', $single);

        $voucher = (new Price())
            ->setVoucherType($this->getReference('voucherType10UnitsHalfDay', VoucherType::class))
            ->setAmount(PriceFixtures::HALF_DAY_VOUCHER_PRICE_AMOUNT)
            ->setIsActive(true)
            ->setBookingType($halfDay)
            ->setName('10er-Ticket Halbtag')
        ;
        $manager->persist($voucher);
        $manager->flush();

        $this->addReference('price-voucher-half-day', $voucher);
    }
}
