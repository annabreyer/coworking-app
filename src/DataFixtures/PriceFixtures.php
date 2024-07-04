<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Price;
use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PriceFixtures extends Fixture implements DependentFixtureInterface
{
    public const SINGLE_PRICE_AMOUNT = 1500;

    public function getDependencies()
    {
        return [
            VoucherTypeFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadPrices($manager);
    }

    private function loadPrices(ObjectManager $manager)
    {
        $priceMonthly = new Price();
        $priceMonthly->setIsSubscription(false)
                     ->setAmount(23000)
                     ->setIsActive(false);

        $manager->persist($priceMonthly);

        $priceSingle = new Price();
        $priceSingle->setIsUnitary(true)
                    ->setAmount(self::SINGLE_PRICE_AMOUNT);

        $manager->persist($priceSingle);
        $this->addReference('price-single', $priceSingle);

        $priceVoucher = new Price();
        $priceVoucher->setVoucherType($this->getReference('voucherType10Units', VoucherType::class))
                     ->setAmount(13500);

        $manager->persist($priceVoucher);
        $manager->flush();

        $this->addReference('price-voucher', $priceVoucher);
    }
}
