<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\DataFixtures\BookingTypeFixtures;
use App\Entity\BookingType;
use App\Repository\BookingTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingTypeRepositoryTest extends KernelTestCase
{
    private BookingTypeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([BookingTypeFixtures::class]);
        $this->repository = static::getContainer()->get(BookingTypeRepository::class);
    }

    public function testFindActiveTypesReturnsActiveTypesSortedByName(): void
    {
        $names = array_map(static fn (BookingType $type) => $type->getName(), $this->repository->findActiveTypes());

        self::assertSame([BookingType::NAME_FULL_DAY, BookingType::NAME_HALF_DAY], $names);
    }

    public function testFindActiveTypesExcludesInactiveTypes(): void
    {
        $halfDay = $this->repository->findOneBy(['name' => BookingType::NAME_HALF_DAY]);
        $halfDay->setIsActive(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $names = array_map(static fn (BookingType $type) => $type->getName(), $this->repository->findActiveTypes());

        self::assertSame([BookingType::NAME_FULL_DAY], $names);
    }

    public function testFindFullDayTypeReturnsTheFullDayType(): void
    {
        self::assertSame(BookingType::NAME_FULL_DAY, $this->repository->findFullDayType()?->getName());
    }
}
