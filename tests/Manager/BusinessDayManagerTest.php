<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\BusinessDay;
use App\Manager\BusinessDayManager;
use App\Repository\BusinessDayRepository;
use App\Service\PublicHolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Test\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BusinessDayManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testCreateBusinessDayCreatesBusinessDay(): void
    {
        $businessDayManager = new BusinessDayManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(BusinessDayRepository::class),
            $this->createMock(PublicHolidayService::class)
        );

        $today       = new \DateTimeImmutable();
        $businessDay = $businessDayManager->createBusinessDay($today);

        self::assertInstanceOf(BusinessDay::class, $businessDay);
    }

    public function testCreateBusinessDayCreatesBusinessDayWithGivenDate(): void
    {
        $businessDayManager = new BusinessDayManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(BusinessDayRepository::class),
            $this->createMock(PublicHolidayService::class)
        );

        $today       = new \DateTimeImmutable();
        $businessDay = $businessDayManager->createBusinessDay($today);

        self::assertInstanceOf(BusinessDay::class, $businessDay);
        self::assertSame($today, $businessDay->getDate());
    }

    public function testSaveBusinessDayPersistsBusinessDay(): void
    {
        $businessDayMock   = $this->createMock(BusinessDay::class);
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects(self::once())
            ->method('persist')
            ->with($businessDayMock);

        $businessDayManager = new BusinessDayManager(
            $entityManagerMock,
            $this->createMock(BusinessDayRepository::class),
            $this->createMock(PublicHolidayService::class)
        );

        $businessDayManager->saveBusinessDay($businessDayMock);
    }

    public function testSaveBusinessDayFlushesBusinessDayIfParameterIsEmpty(): void
    {
        $businessDayMock   = $this->createMock(BusinessDay::class);
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects(self::once())
                          ->method('flush');

        $businessDayManager = new BusinessDayManager(
            $entityManagerMock,
            $this->createMock(BusinessDayRepository::class),
            $this->createMock(PublicHolidayService::class)
        );

        $businessDayManager->saveBusinessDay($businessDayMock);
    }

    public function testSaveBusinessDayDoesNotFlushBusinessDayIfParameterIsFalse(): void
    {
        $businessDayMock   = $this->createMock(BusinessDay::class);
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects(self::never())
                          ->method('flush');

        $businessDayManager = new BusinessDayManager(
            $entityManagerMock,
            $this->createMock(BusinessDayRepository::class),
            $this->createMock(PublicHolidayService::class)
        );

        $businessDayManager->saveBusinessDay($businessDayMock, false);
    }

    public function testGetDateRangeEndDateIsGivenEndDatePlusOneDay(): void
    {
        $businessDayRepositoryMock = $this->createMock(BusinessDayRepository::class);
        $businessDayRepositoryMock->expects(self::once())
                                 ->method('findLastBusinessDay')
                                 ->willReturn(null);

        $businessDayManager = new BusinessDayManager(
            $this->createMock(EntityManagerInterface::class),
            $businessDayRepositoryMock,
            $this->createMock(PublicHolidayService::class)
        );

        $endDate = new \DateTimeImmutable('Tomorrow');
        $period  = $businessDayManager->getDateRange($endDate);
        $endDate = new \DateTimeImmutable($endDate->format('Y-m-d') . '+ 1 day');

        self::assertInstanceOf(\DatePeriod::class, $period);
        self::assertSame($endDate->format('Ymd'), $period->getEndDate()->format('Ymd'));
    }

    public function testGetDateRangeStartDateIsLastBusinessDay(): void
    {
        $lastBusinessDate          = new \DateTimeImmutable('2024-10-31');
        $businessDayRepositoryMock = $this->createMock(BusinessDayRepository::class);
        $businessDayRepositoryMock->expects(self::once())
                                  ->method('findLastBusinessDay')
                                  ->willReturn(new BusinessDay($lastBusinessDate))
        ;

        $businessDayManager = new BusinessDayManager(
            $this->createMock(EntityManagerInterface::class),
            $businessDayRepositoryMock,
            $this->createMock(PublicHolidayService::class)
        );

        $endDate = new \DateTimeImmutable('Tomorrow');
        $period  = $businessDayManager->getDateRange($endDate);

        self::assertInstanceOf(\DatePeriod::class, $period);
        self::assertSame($lastBusinessDate->format('Ymd'), $period->getStartDate()->format('Ymd'));
    }

    public function testGetDateRangeStartDateIsNowIfNoLastBusinessDay(): void
    {
        $businessDayRepositoryMock = $this->createMock(BusinessDayRepository::class);
        $businessDayRepositoryMock->expects(self::once())
                                  ->method('findLastBusinessDay')
                                  ->willReturn(null)
        ;

        $businessDayManager = new BusinessDayManager(
            $this->createMock(EntityManagerInterface::class),
            $businessDayRepositoryMock,
            $this->createMock(PublicHolidayService::class)
        );

        $startDate = new \DateTimeImmutable();
        $endDate   = new \DateTimeImmutable('Tomorrow');
        $period    = $businessDayManager->getDateRange($endDate);

        self::assertInstanceOf(\DatePeriod::class, $period);
        self::assertSame($startDate->format('Ymd'), $period->getStartDate()->format('Ymd'));
    }
}
