<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Dto\Withdrawal\WithdrawalResult;
use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\Withdrawal;
use App\Manager\BookingManager;
use App\Manager\RefundManager;
use App\Manager\VoucherManager;
use App\Manager\WithdrawalManager;
use App\Repository\InvoiceRepository;
use App\Service\AdminMailerService;
use App\Service\WithdrawalMailerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\Translation\TranslatorInterface;

class WithdrawalManagerTest extends TestCase
{
    use ClockSensitiveTrait;

    private const NOW = '2026-10-30 10:00:00';

    public function testCanBeProcessedOnlyForReceivedWithdrawalsWithInvoice(): void
    {
        $withdrawal = new Withdrawal();
        self::assertFalse(WithdrawalManager::canBeProcessed($withdrawal));

        $withdrawal->setInvoice(new Invoice());
        self::assertTrue(WithdrawalManager::canBeProcessed($withdrawal));

        $withdrawal->setStatus(Withdrawal::STATUS_PROCESSED);
        self::assertFalse(WithdrawalManager::canBeProcessed($withdrawal));
    }

    public function testProcessWithdrawalThrowsExceptionWhenNotReceived(): void
    {
        $withdrawal = $this->getWithdrawal(new Invoice());
        $withdrawal->setStatus(Withdrawal::STATUS_REJECTED);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Only received withdrawals can be processed.');
        $this->getWithdrawalManager()->processWithdrawal($withdrawal);
    }

    public function testProcessWithdrawalThrowsExceptionWithoutInvoice(): void
    {
        $withdrawal = $this->getWithdrawal(null);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Withdrawal must have an invoice to be processed.');
        $this->getWithdrawalManager()->processWithdrawal($withdrawal);
    }

    public function testProcessWithdrawalThrowsExceptionForPartiallyPaidInvoice(): void
    {
        $invoice = $this->getBookingInvoice('+3 days', 1500);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_TRANSACTION, 500));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Partially paid invoices must be refunded manually.');
        $this->getWithdrawalManager()->processWithdrawal($this->getWithdrawal($invoice));
    }

    public function testProcessWithdrawalThrowsExceptionForGeneralInvoice(): void
    {
        $invoice = (new Invoice())->setAmount(1500)->setUser(new User());

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Only booking and voucher invoices can be processed automatically.');
        $this->getWithdrawalManager()->processWithdrawal($this->getWithdrawal($invoice));
    }

    public function testProcessWithdrawalCancelsFutureUnpaidBookingWithReversalInvoice(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice    = $this->getBookingInvoice('+3 days', 1500);
        $booking    = $invoice->getFirstBooking();
        $withdrawal = $this->getWithdrawal($invoice);

        $bookingManager = $this->createMock(BookingManager::class);
        $bookingManager->expects(self::once())
                       ->method('cancelBookingAfterWithdrawal')
                       ->with($booking)
        ;
        $bookingManager->expects(self::never())
                       ->method('cancelBooking')
        ;

        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::once())
                      ->method('refundInvoiceAfterWithdrawal')
                      ->with($invoice, 1500)
                      ->willReturn(0)
        ;

        $mailer = $this->createMock(WithdrawalMailerService::class);
        $mailer->expects(self::once())
               ->method('sendWithdrawalProcessedEmail')
               ->with($withdrawal, self::isInstanceOf(WithdrawalResult::class))
        ;

        $result = $this->getWithdrawalManager($bookingManager, $refundManager, mailer: $mailer)
                       ->processWithdrawal($withdrawal);

        self::assertSame(Withdrawal::STATUS_PROCESSED, $result->status);
        self::assertSame(WithdrawalResult::REFUND_METHOD_REVERSAL, $result->refundMethod);
        self::assertSame(0, $result->refundAmount);
        self::assertSame(Withdrawal::STATUS_PROCESSED, $withdrawal->getStatus());
        self::assertSame('admin.withdrawal.processed.reversal', $withdrawal->getAdminComment());
    }

    public function testProcessWithdrawalReactivatesVouchersForBookingPaidByVoucher(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getBookingInvoice('+3 days', 1500);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_VOUCHER, 1500));

        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::once())
                      ->method('refundInvoiceAfterWithdrawal')
                      ->with($invoice, 1500)
                      ->willReturn(0)
        ;

        $result = $this->getWithdrawalManager(refundManager: $refundManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(Withdrawal::STATUS_PROCESSED, $result->status);
        self::assertSame(WithdrawalResult::REFUND_METHOD_VOUCHER, $result->refundMethod);
        self::assertFalse($result->needsManualRefund());
    }

    public function testProcessWithdrawalRefundsBookingPaidByPayPalManually(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getBookingInvoice('+3 days', 1500);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_PAYPAL, 1500));

        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::once())
                      ->method('refundInvoiceAfterWithdrawal')
                      ->with($invoice, 1500)
                      ->willReturn(1500)
        ;

        $result = $this->getWithdrawalManager(refundManager: $refundManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(WithdrawalResult::REFUND_METHOD_PAYPAL, $result->refundMethod);
        self::assertSame(1500, $result->refundAmount);
        self::assertTrue($result->needsManualRefund());
    }

    public function testProcessWithdrawalRefundsBookingPaidByTransactionManually(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getBookingInvoice('+3 days', 1500);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_TRANSACTION, 1500));

        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->method('refundInvoiceAfterWithdrawal')
                      ->willReturn(1500)
        ;

        $result = $this->getWithdrawalManager(refundManager: $refundManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(WithdrawalResult::REFUND_METHOD_TRANSACTION, $result->refundMethod);
        self::assertTrue($result->needsManualRefund());
    }

    public function testProcessWithdrawalRejectsPastBooking(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice    = $this->getBookingInvoice('-1 day', 1500);
        $withdrawal = $this->getWithdrawal($invoice);

        $bookingManager = $this->createMock(BookingManager::class);
        $bookingManager->expects(self::never())
                       ->method('cancelBookingAfterWithdrawal')
        ;
        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::never())
                      ->method('refundInvoiceAfterWithdrawal')
        ;
        $mailer = $this->createMock(WithdrawalMailerService::class);
        $mailer->expects(self::once())
               ->method('sendWithdrawalRejectedEmail')
               ->with($withdrawal)
        ;

        $result = $this->getWithdrawalManager($bookingManager, $refundManager, mailer: $mailer)
                       ->processWithdrawal($withdrawal);

        self::assertSame(Withdrawal::STATUS_REJECTED, $result->status);
        self::assertSame(WithdrawalResult::REJECTION_PERFORMED, $result->rejectionReason);
        self::assertSame(Withdrawal::STATUS_REJECTED, $withdrawal->getStatus());
        self::assertSame('withdrawal.result.reason.performed', $withdrawal->getAdminComment());
    }

    public function testProcessWithdrawalRejectsTodaysBookingUnlessMarkedAsUnused(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));

        $result = $this->getWithdrawalManager()
                       ->processWithdrawal($this->getWithdrawal($this->getBookingInvoice('today', 1500)));
        self::assertSame(Withdrawal::STATUS_REJECTED, $result->status);

        $result = $this->getWithdrawalManager()
                       ->processWithdrawal($this->getWithdrawal($this->getBookingInvoice('today', 1500)), null, true);
        self::assertSame(Withdrawal::STATUS_PROCESSED, $result->status);
    }

    public function testProcessWithdrawalRejectsAlreadyCancelledBooking(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getBookingInvoice('+3 days', 1500);
        $invoice->getFirstBooking()?->setIsCancelled(true);

        $result = $this->getWithdrawalManager()
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(Withdrawal::STATUS_REJECTED, $result->status);
        self::assertSame(WithdrawalResult::REJECTION_ALREADY_CANCELLED, $result->rejectionReason);
    }

    public function testProcessWithdrawalUsesAdminComment(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $withdrawal = $this->getWithdrawal($this->getBookingInvoice('-1 day', 1500));

        $this->getWithdrawalManager()->processWithdrawal($withdrawal, '  Tag wurde genutzt.  ');

        self::assertSame('Tag wurde genutzt.', $withdrawal->getAdminComment());
    }

    public function testProcessWithdrawalInvalidatesUnusedVouchersAndKeepsValueCompensation(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getVoucherInvoice(13500, 10, 3);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_PAYPAL, 13500));

        $voucherManager = $this->createMock(VoucherManager::class);
        $voucherManager->expects(self::exactly(7))
                       ->method('invalidateVoucher')
                       ->with(self::callback(static function (Voucher $voucher) {
                           return null === $voucher->getUseDate();
                       }))
        ;

        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::once())
                      ->method('refundInvoiceAfterWithdrawal')
                      ->with($invoice, 9450)
                      ->willReturn(9450)
        ;

        $result = $this->getWithdrawalManager(refundManager: $refundManager, voucherManager: $voucherManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(Withdrawal::STATUS_PROCESSED, $result->status);
        self::assertSame(WithdrawalResult::REFUND_METHOD_PAYPAL, $result->refundMethod);
        self::assertSame(9450, $result->refundAmount);
        self::assertSame(4050, $result->valueCompensation);
    }

    public function testProcessWithdrawalCancelsUnpaidVoucherInvoice(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getVoucherInvoice(13500, 10, 0);

        $voucherManager = $this->createMock(VoucherManager::class);
        $voucherManager->expects(self::exactly(10))
                       ->method('invalidateVoucher')
        ;
        $refundManager = $this->createMock(RefundManager::class);
        $refundManager->expects(self::once())
                      ->method('refundInvoiceAfterWithdrawal')
                      ->with($invoice, 13500)
        ;

        $result = $this->getWithdrawalManager(refundManager: $refundManager, voucherManager: $voucherManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(WithdrawalResult::REFUND_METHOD_REVERSAL, $result->refundMethod);
        self::assertSame(0, $result->valueCompensation);
    }

    public function testProcessWithdrawalRejectsVoucherInvoiceWhenAllVouchersAreUsed(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $invoice = $this->getVoucherInvoice(13500, 10, 10);
        $invoice->addPayment($this->getPayment(Payment::PAYMENT_TYPE_TRANSACTION, 13500));

        $voucherManager = $this->createMock(VoucherManager::class);
        $voucherManager->expects(self::never())
                       ->method('invalidateVoucher')
        ;

        $result = $this->getWithdrawalManager(voucherManager: $voucherManager)
                       ->processWithdrawal($this->getWithdrawal($invoice));

        self::assertSame(Withdrawal::STATUS_REJECTED, $result->status);
        self::assertSame(WithdrawalResult::REJECTION_VOUCHERS_USED, $result->rejectionReason);
    }

    public function testProcessWithdrawalThrowsExceptionWhenVouchersOfUnpaidInvoiceAreUsed(): void
    {
        $invoice = $this->getVoucherInvoice(13500, 10, 2);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Vouchers of an unpaid invoice have been used, the withdrawal must be handled manually.');
        $this->getWithdrawalManager()->processWithdrawal($this->getWithdrawal($invoice));
    }

    public function testProcessWithdrawalLogsFailedResultEmail(): void
    {
        self::mockTime(new \DateTimeImmutable(self::NOW));
        $withdrawal = $this->getWithdrawal($this->getBookingInvoice('-1 day', 1500));

        $mailer = $this->createMock(WithdrawalMailerService::class);
        $mailer->method('sendWithdrawalRejectedEmail')
               ->willThrowException(new TransportException('SMTP down'))
        ;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
               ->method('error')
        ;

        $result = $this->getWithdrawalManager(mailer: $mailer, logger: $logger)->processWithdrawal($withdrawal);

        self::assertSame(Withdrawal::STATUS_REJECTED, $result->status);
    }

    private function getWithdrawal(?Invoice $invoice): Withdrawal
    {
        return (new Withdrawal())
            ->setName('User One')
            ->setEmail('user.one@annabreyer.dev')
            ->setContractReference((string) $invoice?->getNumber())
            ->setReceivedAt(new \DateTimeImmutable(self::NOW))
            ->setInvoice($invoice)
        ;
    }

    private function getBookingInvoice(string $businessDayModifier, int $amount): Invoice
    {
        $businessDay = new BusinessDay((new \DateTimeImmutable(self::NOW))->modify($businessDayModifier));
        $booking     = (new Booking())->setBusinessDay($businessDay)->setAmount($amount);

        $invoice = (new Invoice())
            ->setNumber('CO20260001')
            ->setAmount($amount)
            ->setUser(new User())
        ;
        $invoice->addBooking($booking);

        return $invoice;
    }

    private function getVoucherInvoice(int $amount, int $units, int $usedUnits): Invoice
    {
        $invoice = (new Invoice())
            ->setNumber('CO20260002')
            ->setAmount($amount)
            ->setUser(new User())
        ;

        for ($i = 0; $i < $units; ++$i) {
            $voucher = (new Voucher())
                ->setValue(intdiv($amount, $units))
                ->setExpiryDate(new \DateTimeImmutable('+1 year'))
                ->setUseDate($i < $usedUnits ? new \DateTimeImmutable(self::NOW) : null)
            ;
            $invoice->addVoucher($voucher);
        }

        return $invoice;
    }

    private function getPayment(string $type, int $amount): Payment
    {
        return (new Payment())
            ->setType($type)
            ->setAmount($amount)
            ->setDate(new \DateTimeImmutable(self::NOW))
        ;
    }

    private function getWithdrawalManager(
        ?BookingManager $bookingManager = null,
        ?RefundManager $refundManager = null,
        ?VoucherManager $voucherManager = null,
        ?WithdrawalMailerService $mailer = null,
        ?LoggerInterface $logger = null,
    ): WithdrawalManager {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
                   ->willReturnArgument(0)
        ;

        return new WithdrawalManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(InvoiceRepository::class),
            $mailer ?? $this->createMock(WithdrawalMailerService::class),
            $this->createMock(AdminMailerService::class),
            $logger         ?? $this->createMock(LoggerInterface::class),
            $bookingManager ?? $this->createMock(BookingManager::class),
            $voucherManager ?? $this->createMock(VoucherManager::class),
            $refundManager  ?? $this->createMock(RefundManager::class),
            $translator,
        );
    }
}
