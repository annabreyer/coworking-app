<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VoucherTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->loadVoucherTypes($manager);
    }

    private function loadVoucherTypes(ObjectManager $manager): void
    {
        $voucherType10 = new VoucherType();
        $voucherType10->setUnits(10);
        $voucherType10->setValidityMonths(12);
        $voucherType10->setUnitaryValue(1500);

        $manager->persist($voucherType10);
        $manager->flush();

        $this->addReference('voucherType10Units', $voucherType10);

        $voucherType = new VoucherType();
        $voucherType->setValidityMonths(1)
                    ->setUnits(1)
            ->setUnitaryValue(1500);

        $manager->persist($voucherType);
        $manager->flush();

        $this->addReference('single-use-voucher-type', $voucherType);
    }
}
