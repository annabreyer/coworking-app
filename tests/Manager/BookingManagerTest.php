<?php

declare(strict_types = 1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\User;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use App\Service\BookingMailerService;
use App\Service\InvoiceMailerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BookingManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testHandleFinalizedBookingThrowsExceptionWhenNoInvoice(): void
    {
        $booking        = new Booking();
        $bookingManager = $this->getBookingManagerWithMocks('1');

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Booking must have an invoice to be finalized.');
        $bookingManager->handleFinalizedBooking($booking);
    }

    public function testHandleFinalizedBookingSendFirstBookingEmail()
    {
        $userMock = $this->createMock(User::class);
        $userMock->method('getBookings')
                 ->willReturn(new ArrayCollection([new Booking()]))
        ;

        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getUser')
                    ->willReturn($userMock)
        ;
        $invoiceMock = $this->createMock(Invoice::class);
        $bookingMock->method('getInvoice')
                    ->willReturn($invoiceMock)
        ;

        $mailerMock = $this->createMock(BookingMailerService::class);
        $mailerMock->expects($this->once())
                   ->method('sendFirstBookingEmail')
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $mailerMock);
        $bookingManager->handleFinalizedBooking($bookingMock);
    }

    public function testHandleFinalizedBookingDoNotSendEmailIfMultipleBookings()
    {
        $mailerMock = $this->createMock(BookingMailerService::class);
        $mailerMock->expects($this->never())
                   ->method('sendFirstBookingEmail')
        ;

        $userMock = $this->createMock(User::class);
        $userMock->method('getBookings')
                 ->willReturn(new ArrayCollection([new Booking(), new Booking()]))
        ;

        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getUser')
                    ->willReturn($userMock)
        ;
        $invoiceMock = $this->createMock(Invoice::class);
        $bookingMock->method('getInvoice')
                    ->willReturn($invoiceMock)
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $mailerMock);
        $bookingManager->handleFinalizedBooking($bookingMock);
    }

    public function testCancelBookingRefundsFullyPaidBooking(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('isFullyPaid')
                    ->willReturn(true)
        ;
        $bookingMock->method('getInvoice')
                    ->willReturn($this->createMock(Invoice::class))
        ;

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $invoiceManagerMock
            ->expects($this->never())
            ->method('cancelUnpaidInvoice')
        ;

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects($this->once())
            ->method('sendBookingCancelledEmail')
            ->with($bookingMock)
        ;

        $bookingManagerMock = $this->getMockBuilder(BookingManager::class)
                                   ->onlyMethods(['refundBooking'])
                                   ->setConstructorArgs([
                                       $this->createMock(EntityManagerInterface::class),
                                       $this->createMock(VoucherManager::class),
                                       $invoiceManagerMock,
                                       $bookingMailerMock,
                                       $this->createMock(InvoiceMailerService::class),
                                       '1',
                                   ])
                                   ->getMock()
        ;

        $bookingManagerMock->expects($this->once())
                           ->method('refundBooking')
                           ->with($bookingMock)
        ;

        $bookingManagerMock->cancelBooking($bookingMock);
    }

    public function testCancelBookingCancelsUnpaidInvoice(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('isFullyPaid')
                    ->willReturn(false)
        ;
        $bookingMock->method('getInvoice')
                    ->willReturn($this->createMock(Invoice::class))
        ;

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $invoiceManagerMock
            ->expects($this->once())
            ->method('cancelUnpaidInvoice')
        ;

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects($this->once())
            ->method('sendBookingCancelledEmail')
            ->with($bookingMock)
        ;

        $bookingManagerMock = $this->getMockBuilder(BookingManager::class)
                                   ->onlyMethods(['refundBooking'])
                                   ->setConstructorArgs([
                                       $this->createMock(EntityManagerInterface::class),
                                       $this->createMock(VoucherManager::class),
                                       $invoiceManagerMock,
                                       $bookingMailerMock,
                                       $this->createMock(InvoiceMailerService::class),
                                       '1',
                                   ])
                                   ->getMock()
        ;

        $bookingManagerMock->expects($this->never())
                           ->method('refundBooking')
                           ->with($bookingMock)
        ;

        $bookingManagerMock->cancelBooking($bookingMock);
    }

    public function testCancelBookingDoesNothingIfBookingHasNoInvoice(): void
    {
        $bookingMock = $this->createMock(Booking::class);

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects($this->never())
            ->method('sendBookingCancelledEmail')
            ->with($bookingMock)
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $bookingMailerMock);
        $bookingManager->cancelBooking($bookingMock);
    }

    public function testCanBookingBeCancelledByUserThrowsExceptionIfNoTimeLimit(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingBeCancelledByUserThrowsExceptionIfTimeLimitIsHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('0.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingBeCancelledByUserThrowsExceptionIfTimeLimitIsOneAndHalfDay(): void
    {
        $bookingManager = $this->getBookingManagerWithMocks('1.5');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTime());
        $booking->setBusinessDay($businessDay);

        static::expectException(\LogicException::class);
        static::expectExceptionMessage('Time limit cancel booking is wrongly configured.');
        $bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate());
    }

    public function testCanBookingBeCancelledByUserReturnsTrueIfBookingIsInTheFuture(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-03-16'));
        $booking->setBusinessDay($businessDay);

        self::assertTrue($bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate()));
    }

    public function testCanBookingBeCancelledByUserReturnsFalseIfBookingIsInThePast(): void
    {
        self::mockTime(new \DateTimeImmutable('2024-03-01'));
        $bookingManager = $this->getBookingManagerWithMocks('1');
        $booking        = new Booking();
        $businessDay    = new BusinessDay(new \DateTimeImmutable('2024-02-16'));
        $booking->setBusinessDay($businessDay);

        self::assertFalse($bookingManager->canBookingBeCancelledByUser($booking->getBusinessDay()->getDate()));
    }

    public function testRefundBookingThrowsExceptionWhenBookingHasNoAmount(): void
    {
        $booking = new Booking();

        $bookingManager = $this->getBookingManagerWithMocks('1');
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Booking must have an amount to be refunded.');

        $bookingManager->refundBooking($booking);
    }

    public function testRefundBookingThrowsExceptionWhenBookingHasNoInvoice(): void
    {
        $booking = (new Booking())->setAmount(100);

        $bookingManager = $this->getBookingManagerWithMocks('1');
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Booking must have an invoice to be refunded.');

        $bookingManager->refundBooking($booking);
    }

    public function testRefundBookingThrowsExceptionWhenBookingIsNotFullyPaid(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('isFullyPaid')
                    ->willReturn(false)
        ;
        $bookingMock->method('getInvoice')
                    ->willReturn($this->createMock(Invoice::class))
        ;
        $bookingMock->method('getAmount')
            ->willReturn(1100);

        $bookingManager = $this->getBookingManagerWithMocks('1');
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Booking invoice must be fully paid to be refunded.');

        $bookingManager->refundBooking($bookingMock);
    }

    public function testRefundBookingThrowsExceptionWhenBookingHasNoUser(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('isFullyPaid')
                    ->willReturn(true)
        ;
        $bookingMock->method('getInvoice')
                    ->willReturn($this->createMock(Invoice::class))
        ;
        $bookingMock->method('getAmount')
                    ->willReturn(1100);

        $bookingManager = $this->getBookingManagerWithMocks('1');
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Booking must have a user to be refunded.');

        $bookingManager->refundBooking($bookingMock);
    }

    private function getBookingManagerWithMocks(
        string $timeLimit,
        BookingMailerService|null $bookingMailerService = null
    ): BookingManager {
        if (null === $bookingMailerService) {
            $bookingMailerService = $this->createMock(BookingMailerService::class);
        }

        return new BookingManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(VoucherManager::class),
            $this->createMock(InvoiceManager::class),
            $bookingMailerService,
            $this->createMock(InvoiceMailerService::class),
            $timeLimit
        );
    }
}
