<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BookingType;
use App\Entity\Price;
use App\Entity\VoucherType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PriceFixtures extends Fixture implements DependentFixtureInterface
{
    public const SINGLE_PRICE_AMOUNT = 1500;
    public const HALF_DAY_SINGLE_PRICE_AMOUNT = 1200;
    public const HALF_DAY_VOUCHER_PRICE_AMOUNT = 10000;

    public function getDependencies(): array
    {
        return [
            VoucherTypeFixtures::class,
            BookingTypeFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadPrices($manager);
    }

    private function loadPrices(ObjectManager $manager)
    {
        $fullDay = $this->getReference(BookingTypeFixtures::FULL_DAY_REFERENCE, BookingType::class);

        $priceMonthly = new Price();
        $priceMonthly->setIsSubscription(true)
                     ->setAmount(23000)
                     ->setIsActive(false)
                     ->setBookingType($fullDay)
                        ->setName('Monatsabo');

        $manager->persist($priceMonthly);

        $priceSingle = new Price();
        $priceSingle->setIsUnitary(true)
                    ->setAmount(self::SINGLE_PRICE_AMOUNT)
                    ->setIsActive(true)
                    ->setBookingType($fullDay)
                    ->setName('Einzelticket');

        $manager->persist($priceSingle);
        $this->addReference('price-single', $priceSingle);

        $priceVoucher = new Price();
        $priceVoucher->setVoucherType($this->getReference('voucherType10Units', VoucherType::class))
                     ->setAmount(13500)
                     ->setBookingType($fullDay)
                        ->setIsActive(true)
                        ->setName('10er-Ticket');

        $manager->persist($priceVoucher);
        $manager->flush();

        $this->addReference('price-voucher', $priceVoucher);
    }
}
