<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testCanBookingBeCancelledThrowsExceptionForMissingBusinessDayOrDate(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Booking must have a business day and a date.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfNoTimeLimit(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsOneAndHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('1.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking);
    }

    public function testCanBookingReturnsTrueIfBookingIsInTheFuture(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-03-16'));
        $booking->setBusinessDay($businessDay);

        self::assertTrue($bookingManager->canBookingBeCancelledByUser($booking));
    }

    public function testCanBookingReturnsFalseIfBookingIsInThePast(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-02-16'));
        $booking->setBusinessDay($businessDay);

        self::assertFalse($bookingManager->canBookingBeCancelledByUser($booking));
    }

    private function getBookingManagerWithMocks(string $timeLimit): BookingManager
    {
        return new BookingManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(VoucherManager::class),
            $this->createMock(InvoiceManager::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(TranslatorInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $timeLimit
        );
    }
}
