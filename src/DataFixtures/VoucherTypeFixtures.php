<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BookingType;
use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VoucherTypeFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [BookingTypeFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadVoucherTypes($manager);
    }

    private function loadVoucherTypes(ObjectManager $manager): void
    {
        $fullDay = $this->getReference(BookingTypeFixtures::FULL_DAY_REFERENCE, BookingType::class);
        $halfDay = $this->getReference(BookingTypeFixtures::HALF_DAY_REFERENCE, BookingType::class);

        $voucherType10 = new VoucherType();
        $voucherType10->setUnits(10);
        $voucherType10->setValidityMonths(12);
        $voucherType10->setUnitaryValue(1500);
        $voucherType10->setBookingType($fullDay);

        $manager->persist($voucherType10);
        $manager->flush();

        $this->addReference('voucherType10Units', $voucherType10);

        $voucherType10Half = new VoucherType();
        $voucherType10Half->setUnits(10);
        $voucherType10Half->setValidityMonths(12);
        $voucherType10Half->setUnitaryValue(1000);
        $voucherType10Half->setBookingType($halfDay);

        $manager->persist($voucherType10Half);
        $manager->flush();

        $this->addReference('voucherType10UnitsHalfDay', $voucherType10Half);

        $voucherType = new VoucherType();
        $voucherType->setValidityMonths(1)
                    ->setUnits(1)
            ->setUnitaryValue(1500)
            ->setBookingType($fullDay);

        $manager->persist($voucherType);
        $manager->flush();

        $this->addReference('single-use-voucher-type', $voucherType);

        $voucherTypeRefund = new VoucherType();
        $voucherTypeRefund->setUnits(1);
        $voucherTypeRefund->setValidityMonths(12);
        $voucherTypeRefund->setUnitaryValue(0);
        $voucherTypeRefund->setName(VoucherType::NAME_REFUND);

        $manager->persist($voucherTypeRefund);
        $manager->flush();
    }
}
