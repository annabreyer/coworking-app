<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use App\Service\BookingMailerService;
use App\Service\InvoiceMailerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BookingManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testCanBookingBeCancelledThrowsExceptionIfNoTimeLimit(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsOneAndHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('1.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingReturnsTrueIfBookingIsInTheFuture(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-03-16'));
        $booking->setBusinessDay($businessDay);

        self::assertTrue($bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate()));
    }

    public function testCanBookingReturnsFalseIfBookingIsInThePast(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-02-16'));
        $booking->setBusinessDay($businessDay);

        self::assertFalse($bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate()));
    }

    private function getBookingManagerWithMocks(string $timeLimit): BookingManager
    {
        return new BookingManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(VoucherManager::class),
            $this->createMock(InvoiceManager::class),
            $this->createMock(BookingMailerService::class),
            $this->createMock(InvoiceMailerService::class),
            $timeLimit
        );
    }
}
