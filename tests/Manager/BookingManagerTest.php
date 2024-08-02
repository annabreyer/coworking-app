<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Manager\BookingManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BookingManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testCanBookingBeCancelledThrowsExceptionForMissingBusinessDayOrDate(): void
    {
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '1');
        $booking           = new Booking();

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Booking must have a business day and a date.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfNoTimeLimit(): void
    {
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '0');
        $booking           = new Booking();
        $businessDay       = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsHalfDay(): void
    {
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '0.5');
        $booking           = new Booking();
        $businessDay       = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsOneAndHalfDay(): void
    {
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '1.5');
        $booking           = new Booking();
        $businessDay       = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingReturnsTrueIfBookingIsInTheFuture(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '1');
        $booking           = new Booking();
        $businessDay       = new BusinessDay(new \DateTimeImmutable('2024-03-16'));
        $booking->setBusinessDay($businessDay);

        self::assertTrue($bookingManager->canBookingBeCancelledByUser($booking));
    }

    public function testCanBookingReturnsFalseIfBookingIsInThePast(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $bookingManager    = new BookingManager($mockEntityManager, '1');
        $booking           = new Booking();
        $businessDay       = new BusinessDay(new \DateTimeImmutable('2024-02-16'));
        $booking->setBusinessDay($businessDay);

        self::assertFalse($bookingManager->canBookingBeCancelledByUser($booking));
    }
}
