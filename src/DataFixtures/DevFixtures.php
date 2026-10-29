<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Price;
use App\Entity\VoucherType;
use App\Manager\BusinessDayManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class DevFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use ClockAwareTrait;

    public const BOOKABLE_MONTHS           = 6;
    public const FIVE_UNITS_VOUCHER_AMOUNT = 7000;

    public function __construct(
        private readonly BusinessDayManager $businessDayManager,
    ) {
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
            PriceFixtures::class,
            VoucherFixtures::class,
            InvoiceFixtures::class,
            BookingWithInvoiceNoPaymentFixture::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadFiveUnitsVoucherPrice($manager);
        $this->loadBookableBusinessDays();
    }

    private function loadFiveUnitsVoucherPrice(ObjectManager $manager): void
    {
        $voucherType = new VoucherType();
        $voucherType->setUnits(5)
                    ->setValidityMonths(6)
                    ->setUnitaryValue(PriceFixtures::SINGLE_PRICE_AMOUNT)
        ;
        $manager->persist($voucherType);

        $price = new Price();
        $price->setVoucherType($voucherType)
              ->setAmount(self::FIVE_UNITS_VOUCHER_AMOUNT)
              ->setIsActive(true)
              ->setName('5er-Ticket')
        ;
        $manager->persist($price);

        $manager->flush();
    }

    private function loadBookableBusinessDays(): void
    {
        $lastBookableDay = $this->now()
                                ->modify(\sprintf('+%d months', self::BOOKABLE_MONTHS))
        ;

        $this->businessDayManager->generateBusinessDaysUntil($lastBookableDay);
    }
}
