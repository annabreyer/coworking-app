<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BookingManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testCanBookingBeCancelledThrowsExceptionForMissingBusinessDayOrDate(): void
    {
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);

        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager,'1 day');
        $booking        = new Booking();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Booking must have a business day and a date.');
        $bookingManager->canBookingBeCancelled($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfNoTimeLimit(): void
    {
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);


        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager,'0 day');
        $booking        = new Booking();
        $businessDay    = new BusinessDay();
        $businessDay->setDate(new \DateTime());
        $booking->setBusinessDay($businessDay);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelled($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsHalfDay(): void
    {
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);

        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager,'0.5 day');
        $booking        = new Booking();
        $businessDay    = new BusinessDay();
        $businessDay->setDate(new \DateTime());
        $booking->setBusinessDay($businessDay);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelled($booking);
    }

    public function testCanBookingBeCancelledThrowsExceptionIfTimeLimitIsOneAndHalfDay(): void
    {
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);

        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager, '1.5 day');
        $booking        = new Booking();
        $businessDay    = new BusinessDay();
        $businessDay->setDate(new \DateTime());
        $booking->setBusinessDay($businessDay);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelled($booking);
    }

    public function testCanBookingReturnsTrueIfBookingIsInTheFuture(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);


        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager,'1 day');
        $booking        = new Booking();
        $businessDay    = new BusinessDay();
        $businessDay->setDate(new \DateTimeImmutable('2024-03-16'));
        $booking->setBusinessDay($businessDay);

        self::assertTrue($bookingManager->canBookingBeCancelled($booking));
    }

    public function testCanBookingReturnsFalseIfBookingIsInThePast(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $mockEntityManager  = $this->createMock(EntityManagerInterface::class);
        $mockInvoiceManager = $this->createMock(InvoiceManager::class);
        $mockPaymentManager = $this->createMock(PaymentManager::class);

        $bookingManager = new BookingManager($mockEntityManager, $mockInvoiceManager, $mockPaymentManager, '1 day');
        $booking        = new Booking();
        $businessDay    = new BusinessDay();
        $businessDay->setDate(new \DateTimeImmutable('2024-02-16'));
        $booking->setBusinessDay($businessDay);

        self::assertFalse($bookingManager->canBookingBeCancelled($booking));
    }
}
