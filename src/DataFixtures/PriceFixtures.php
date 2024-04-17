<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Price;
use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PriceFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $this->loadPrices($manager);
    }

    private function loadPrices(ObjectManager $manager)
    {
        $this->loadVoucherTypes($manager);

        $priceMonthly = new Price();
        $priceMonthly->setIsSubscription(false)
                     ->setAmount(23000)
                     ->setIsActive(false);

        $manager->persist($priceMonthly);

        $priceSingle = new Price();
        $priceSingle->setIsUnitary(true)
                    ->setAmount(1500);

        $manager->persist($priceSingle);
        $this->addReference('price-single', $priceSingle);

        $priceVoucher = new Price();
        $priceVoucher->setVoucherType($this->getReference('voucherType10Units', VoucherType::class))
                     ->setAmount(13500);

        $manager->persist($priceVoucher);
        $manager->flush();

        $this->addReference('price-voucher', $priceVoucher);
    }

    private function loadVoucherTypes(ObjectManager $manager): void
    {
        $voucherType = new VoucherType();
        $voucherType->setUnits(10);
        $voucherType->setValidityMonths(12);

        $manager->persist($voucherType);
        $manager->flush();

        $this->addReference('voucherType10Units', $voucherType);
    }
}
