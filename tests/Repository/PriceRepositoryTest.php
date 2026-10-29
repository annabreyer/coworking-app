<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\DataFixtures\HalfDayPriceFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\BookingType;
use App\Repository\BookingTypeRepository;
use App\Repository\PriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PriceRepositoryTest extends KernelTestCase
{
    private PriceRepository $priceRepository;
    private BookingTypeRepository $bookingTypeRepository;

    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([HalfDayPriceFixtures::class]);
        $this->priceRepository       = static::getContainer()->get(PriceRepository::class);
        $this->bookingTypeRepository = static::getContainer()->get(BookingTypeRepository::class);
    }

    public function testFindActiveUnitaryPriceReturnsThePriceOfTheGivenBookingType(): void
    {
        $fullDay = $this->bookingTypeRepository->findOneBy(['name' => BookingType::NAME_FULL_DAY]);
        $halfDay = $this->bookingTypeRepository->findOneBy(['name' => BookingType::NAME_HALF_DAY]);

        self::assertSame(PriceFixtures::SINGLE_PRICE_AMOUNT, $this->priceRepository->findActiveUnitaryPrice($fullDay)?->getAmount());
        self::assertSame(PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT, $this->priceRepository->findActiveUnitaryPrice($halfDay)?->getAmount());
    }

    public function testFindActiveUnitaryPriceReturnsNullWhenThePriceIsInactive(): void
    {
        $halfDay = $this->bookingTypeRepository->findOneBy(['name' => BookingType::NAME_HALF_DAY]);
        $this->priceRepository->findActiveUnitaryPrice($halfDay)->setIsActive(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertNull($this->priceRepository->findActiveUnitaryPrice($halfDay));
    }

    public function testFindActiveUnitaryPricesReturnsOnePricePerActiveBookingType(): void
    {
        $types = array_map(static fn ($price) => $price->getBookingType()->getName(), $this->priceRepository->findActiveUnitaryPrices());

        self::assertSame([BookingType::NAME_FULL_DAY, BookingType::NAME_HALF_DAY], $types);
    }

    public function testFindActiveUnitaryPricesSkipsInactiveBookingTypes(): void
    {
        $this->bookingTypeRepository->findOneBy(['name' => BookingType::NAME_HALF_DAY])->setIsActive(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $types = array_map(static fn ($price) => $price->getBookingType()->getName(), $this->priceRepository->findActiveUnitaryPrices());

        self::assertSame([BookingType::NAME_FULL_DAY], $types);
    }

    public function testFindActiveVoucherPricesIncludesThePricesOfAllBookingTypes(): void
    {
        $amounts = array_map(static fn ($price) => $price->getAmount(), $this->priceRepository->findActiveVoucherPrices());
        sort($amounts);

        self::assertSame([PriceFixtures::HALF_DAY_VOUCHER_PRICE_AMOUNT, 13500], $amounts);
    }
}
