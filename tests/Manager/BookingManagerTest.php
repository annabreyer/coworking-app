<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\RefundManager;
use App\Manager\VoucherManager;
use App\Service\BookingMailerService;
use App\Service\InvoiceMailerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
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
        $mailerMock->expects(self::once())
                   ->method('sendFirstBookingEmail')
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $mailerMock);
        $bookingManager->handleFinalizedBooking($bookingMock);
    }

    public function testHandleFinalizedBookingDoNotSendEmailIfMultipleBookings()
    {
        $mailerMock = $this->createMock(BookingMailerService::class);
        $mailerMock->expects(self::never())
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

    public function testCancelBookingOnlyCancelsBookingIfBookingHasNoInvoice(): void
    {
        $booking = new Booking();

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects(self::never())
            ->method('sendBookingCancelledEmail')
            ->with($booking)
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $bookingMailerMock);
        $bookingManager->cancelBooking($booking);
    }

    public function testCancelBookingSendsBookingCancelledEmailWhenBookingHasInvoice(): void
    {
        $booking = new Booking();
        $invoice = new Invoice();
        $booking->setInvoice($invoice);

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects(self::once())
            ->method('sendBookingCancelledEmail')
            ->with($booking)
        ;

        $bookingManager = $this->getBookingManagerWithMocks('1', $bookingMailerMock);
        $bookingManager->cancelBooking($booking);
    }

    public function testCancelBookingSendsBookingCallsRefundBookingWhenBookingHasInvoice(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $invoiceMock = $this->createMock(Invoice::class);
        $bookingMock->method('getInvoice')
                    ->willReturn($invoiceMock);

        $bookingMailerMock = $this->createMock(BookingMailerService::class);
        $bookingMailerMock
            ->expects(self::once())
            ->method('sendBookingCancelledEmail')
            ->with($bookingMock);

        $bookingManagerMock = $this->getMockBuilder(BookingManager::class)
                                   ->onlyMethods(['refundBooking'])
                                   ->setConstructorArgs([
                                       $this->createMock(EntityManagerInterface::class),
                                       $this->createMock(VoucherManager::class),
                                       $this->createMock(InvoiceManager::class),
                                       $bookingMailerMock,
                                       $this->createMock(InvoiceMailerService::class),
                                       $this->createMock(RefundManager::class),
                                       '1',
                                   ])
                                   ->getMock()
        ;

        $bookingManagerMock->expects(self::once())
                           ->method('refundBooking')
                           ->with($bookingMock);

        $bookingManagerMock->cancelBooking($bookingMock);
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

    public function testRefundBookingThrowsExceptionWhenBookingHasNoInvoice(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getAmount')
                    ->willReturn(1100);

        $bookingManager = $this->getBookingManagerWithMocks('1');
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Booking must have an invoice to be refunded.');

        $bookingManager->refundBooking($bookingMock);
    }

    public function testRefundBookingCallsCancelUnpaidInvoiceIfInvoiceHasNoPayments(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([]));

        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getInvoice')
                    ->willReturn($invoiceMock);

        $refundManagerMock = $this->createMock(RefundManager::class);
        $refundManagerMock->expects(self::once())
                           ->method('refundInvoiceWithReversalInvoice')
                           ->with($invoiceMock);

        $bookingManager = $this->getBookingManagerWithMocks('1', null, null, $refundManagerMock);

        $bookingManager->refundBooking($bookingMock);
    }

    public function testRefundBookingCallsRefundInvoice(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_TRANSACTION)]));

        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getInvoice')
                    ->willReturn($invoiceMock);

        $refundManagerMock = $this->createMock(RefundManager::class);
        $refundManagerMock->expects(self::once())
                         ->method('refundInvoiceWithVoucher')
                         ->with($invoiceMock);

        $bookingManager = $this->getBookingManagerWithMocks('1', null, null, $refundManagerMock);

        $bookingManager->refundBooking($bookingMock);
    }

    private function getBookingManagerWithMocks(
        string $timeLimit, ?BookingMailerService $bookingMailerService = null, ?InvoiceManager $invoiceManagerMock = null, ?RefundManager $refundManagerMock = null,
    ): BookingManager {
        if (null === $bookingMailerService) {
            $bookingMailerService = $this->createMock(BookingMailerService::class);
        }

        if (null === $invoiceManagerMock) {
            $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        }

        if (null === $refundManagerMock) {
            $refundManagerMock = $this->createMock(RefundManager::class);
        }

        return new BookingManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(VoucherManager::class),
            $invoiceManagerMock,
            $bookingMailerService,
            $this->createMock(InvoiceMailerService::class),
            $refundManagerMock,
            $timeLimit
        );
    }
}
