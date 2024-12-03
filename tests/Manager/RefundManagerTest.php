<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Manager\InvoiceManager;
use App\Manager\RefundManager;
use App\Manager\VoucherManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RefundManagerTest extends TestCase
{
    public function testRefundInvoiceThrowsExceptionWhenInvoiceHasNoPaymentAttached(): void
    {
        $invoice       = new Invoice();
        $refundManager = $this->getRefundManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Only invoices with payments can be refunded by voucher. Use refundWithReversalInvoice instead.');
        $refundManager->refundInvoiceWithVoucher($invoice);
    }

    public function testRefundInvoiceCallsCreateRefundVoucherIfPaymentIsOtherThanVoucher(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_PAYPAL)]))
        ;
        $invoiceMock->method('isFullyPaidByVoucher')
                    ->willReturn(false)
        ;
        $invoiceMock->method('getUser')
                    ->willReturn($this->createMock(User::class))
        ;
        $invoiceMock->method('getAmount')
                    ->willReturn(1000)
        ;

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $voucherManagerMock->expects(self::once())
                           ->method('createRefundVoucher')
                           ->with($invoiceMock)
        ;

        $refundManager = $this->getRefundManager($voucherManagerMock);
        $refundManager->refundInvoiceWithVoucher($invoiceMock);
    }

    public function testRefundInvoiceCallsRefundInvoiceVoucherPaymentsIfInvoiceIsFullyPaidByVoucher(): void
    {
        $voucherPayments = new ArrayCollection([
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
        ]);

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn($voucherPayments);
        $invoiceMock->method('isFullyPaidByVoucher')
                    ->willReturn(true);

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $translatorMock     = $this->createMock(TranslatorInterface::class);
        $entityManagerMock  = $this->createMock(EntityManagerInterface::class);
        $invoiceManagerMock = $this->createMock(InvoiceManager::class);

        $refundManagerMock = $this->getMockBuilder(RefundManager::class)
                                  ->setConstructorArgs([
                                      $voucherManagerMock,
                                      $translatorMock,
                                      $entityManagerMock,
                                      $invoiceManagerMock,
                                  ])
                                  ->onlyMethods(['refundInvoiceVoucherPayments'])
                                  ->getMock()
        ;

        $refundManagerMock->expects(self::once())
                          ->method('refundInvoiceVoucherPayments')
                          ->with($voucherPayments)
        ;

        $refundManagerMock->refundInvoiceWithVoucher($invoiceMock);
    }

    public function testRefundInvoiceVoucherPaymentsThrowsExceptionWhenPaymentsAreEmpty(): void
    {
        $voucherPayments = new ArrayCollection([]);
        $refundManager   = $this->getRefundManager();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('There must at least be one voucher payment to be refunded.');

        $refundManager->refundInvoiceVoucherPayments($voucherPayments);
    }

    public function testRefundInvoiceVoucherPaymentsThrowsExceptionWhenVoucherPaymentsHaveNoVoucher(): void
    {
        $voucherPayments = new ArrayCollection([
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
            (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
        ]);

        $refundManager = $this->getRefundManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Voucher Payment needs a voucher attached.');

        $refundManager->refundInvoiceVoucherPayments($voucherPayments);
    }

    public function testRefundInvoiceVoucherPaymentsCallsResetExpiryDateOnlyForVoucherPayments(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('Yesterday'));
        $voucherPayment = new Payment();
        $voucherPayment->setVoucher($voucher)
        ->setType(Payment::PAYMENT_TYPE_VOUCHER);
        $otherPayment = (new Payment())->setType(Payment::PAYMENT_TYPE_TRANSACTION);

        $voucherPayments = new ArrayCollection([$voucherPayment, $otherPayment]);

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $voucherManagerMock->expects(self::once())
                           ->method('resetExpiryDate')
                           ->with($voucher)
        ;

        $refundManager = $this->getRefundManager($voucherManagerMock);
        $refundManager->refundInvoiceVoucherPayments($voucherPayments);
    }

    public function testRefundInvoiceCallsCreateRefundVoucherIfPaymentIsOtherThanFullyVoucher(): void
    {
        $user          = $this->createMock(User::class);
        $invoiceAmount = 1000;
        $invoiceMock   = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([
                        (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
                        (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
                    ]))
        ;
        $invoiceMock->method('isFullyPaidByVoucher')
                    ->willReturn(false)
        ;
        $invoiceMock->method('getUser')
                    ->willReturn($user)
        ;
        $invoiceMock->method('getAmount')
                    ->willReturn($invoiceAmount)
        ;

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $voucherManagerMock->expects(self::once())
                           ->method('createRefundVoucher')
                           ->with($invoiceMock)
        ;

        $refundManager = $this->getRefundManager($voucherManagerMock);
        $refundManager->refundInvoiceWithVoucher($invoiceMock);
    }

    public function testRefundInvoiceCallsCreateRefundVoucherIfPaymentIsSeveralVouchers(): void
    {
        $user          = $this->createMock(User::class);
        $invoiceAmount = 1000;
        $invoiceMock   = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([
                        (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
                        (new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER),
                    ]))
        ;
        $invoiceMock->method('isFullyPaidByVoucher')
                    ->willReturn(false)
        ;
        $invoiceMock->method('getUser')
                    ->willReturn($user)
        ;
        $invoiceMock->method('getAmount')
                    ->willReturn($invoiceAmount)
        ;

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $voucherManagerMock->expects(self::once())
                           ->method('createRefundVoucher')
                           ->with($invoiceMock)
        ;

        $refundManager = $this->getRefundManager($voucherManagerMock);
        $refundManager->refundInvoiceWithVoucher($invoiceMock);
    }

    public function testRefundInvoiceCallsResetExpiryDateWhenPaymentIsOneVoucher(): void
    {
        $invoiceMock        = $this->createMock(Invoice::class);
        $voucherMock        = $this->createMock(Voucher::class);
        $voucherPaymentMock = $this->createMock(Payment::class);
        $voucherPaymentMock->method('getVoucher')
                           ->willReturn($voucherMock)
        ;
        $voucherPaymentMock->method('getType')
                           ->willReturn(Payment::PAYMENT_TYPE_VOUCHER)
        ;
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([$voucherPaymentMock]))
        ;
        $invoiceMock->method('isFullyPaidByVoucher')
                    ->willReturn(true)
        ;

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $voucherManagerMock->expects(self::once())
                           ->method('resetExpiryDate')
                           ->with($voucherMock)
        ;

        $refundManager = $this->getRefundManager($voucherManagerMock);
        $refundManager->refundInvoiceWithVoucher($invoiceMock);
    }

    public function testRefundInvoiceWithReversalInvoiceThrowsExceptionWhenInvoiceHasZeroRemainingAmount(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(0)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to be refunded with a reversal invoice.');
        $refundManager->refundInvoiceWithReversalInvoice($invoiceMock);
    }

    public function testRefundInvoiceWithReversalInvoiceThrowsExceptionWhenInvoiceHasNegativeRemainingAmount(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(-100)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to be refunded with a reversal invoice.');
        $refundManager->refundInvoiceWithReversalInvoice($invoiceMock);
    }

    public function testRefundInvoiceWithReversalInvoiceThrowsExceptionWhenInvoiceHasAnyPaymentAttached(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(100)
        ;
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_PAYPAL)]))
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice can not have payments for a reversal invoice. Refund by voucher instead.');
        $refundManager->refundInvoiceWithReversalInvoice($invoiceMock);
    }

    public function testRefundInvoiceWithReversalInvoice(): void
    {
        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $translatorMock     = $this->createMock(TranslatorInterface::class);
        $entityManagerMock  = $this->createMock(EntityManagerInterface::class);
        $invoiceManagerMock = $this->createMock(InvoiceManager::class);

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(100)
        ;

        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([]));

        $refundManagerMock = $this->getMockBuilder(RefundManager::class)
                                  ->setConstructorArgs([
                                      $voucherManagerMock,
                                      $translatorMock,
                                      $entityManagerMock,
                                      $invoiceManagerMock,
                                  ])
                                  ->onlyMethods(['addRefundPaymentToInvoice'])
                                  ->getMock()
        ;

        $refundManagerMock->expects(self::once())
                          ->method('addRefundPaymentToInvoice')
                          ->with($invoiceMock)
        ;

        $invoiceManagerMock->expects(self::once())
                           ->method('processReversalInvoice')
                           ->with($invoiceMock)
        ;

        $refundManagerMock->refundInvoiceWithReversalInvoice($invoiceMock);
    }

    public function testAddRefundPaymentToInvoiceThrowsExceptionWhenInvoiceRemainingAmountIsZero(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(0)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to get a refund payment.');
        $refundManager->addRefundPaymentToInvoice($invoiceMock);
    }

    public function testAddRefundPaymentToInvoiceThrowsExceptionWhenInvoiceHasNegativeRemainingAmount(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(-100)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to get a refund payment.');
        $refundManager->addRefundPaymentToInvoice($invoiceMock);
    }

    public function testAddRefundPaymentToInvoiceCreatesRefundPayment(): void
    {
        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $translatorMock     = $this->createMock(TranslatorInterface::class);
        $entityManagerMock  = $this->createMock(EntityManagerInterface::class);
        $invoiceManagerMock = $this->createMock(InvoiceManager::class);

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(100)
        ;

        $refundManagerMock = $this->getMockBuilder(RefundManager::class)
                                  ->setConstructorArgs([
                                      $voucherManagerMock,
                                      $translatorMock,
                                      $entityManagerMock,
                                      $invoiceManagerMock,
                                  ])
                                  ->onlyMethods(['createRefundPaymentForInvoice'])
                                  ->getMock()
        ;

        $refundManagerMock->expects(self::once())
                          ->method('createRefundPaymentForInvoice')
                          ->with($invoiceMock)
        ;

        $refundManagerMock->addRefundPaymentToInvoice($invoiceMock);
    }

    public function testAddRefundPaymentToInvoiceAddRefundPaymentToInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $refundPayment = (new Payment())->setType(Payment::PAYMENT_TYPE_REFUND);

        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $translatorMock     = $this->createMock(TranslatorInterface::class);
        $entityManagerMock  = $this->createMock(EntityManagerInterface::class);
        $invoiceManagerMock = $this->createMock(InvoiceManager::class);

        $refundManagerMock = $this->getMockBuilder(RefundManager::class)
                                  ->setConstructorArgs([
                                      $voucherManagerMock,
                                      $translatorMock,
                                      $entityManagerMock,
                                      $invoiceManagerMock,
                                  ])
                                  ->onlyMethods(['createRefundPaymentForInvoice'])
                                  ->getMock()
        ;

        $refundManagerMock->expects(self::once())
                          ->method('createRefundPaymentForInvoice')
                          ->with($invoice)
                          ->willReturn($refundPayment)
        ;

        $refundManagerMock->addRefundPaymentToInvoice($invoice);

        self::assertSame($refundPayment, $invoice->getPayments()->first());
    }

    public function testAddRefundPaymentToInvoiceCallsEntityManagerFlush(): void
    {
        $voucherManagerMock = $this->createMock(VoucherManager::class);
        $translatorMock     = $this->createMock(TranslatorInterface::class);
        $entityManagerMock  = $this->createMock(EntityManagerInterface::class);
        $invoiceManagerMock = $this->createMock(InvoiceManager::class);

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(100)
        ;

        $refundManagerMock = $this->getMockBuilder(RefundManager::class)
                                  ->setConstructorArgs([
                                      $voucherManagerMock,
                                      $translatorMock,
                                      $entityManagerMock,
                                      $invoiceManagerMock,
                                  ])
                                  ->onlyMethods(['createRefundPaymentForInvoice'])
                                  ->getMock()
        ;

        $refundManagerMock->expects(self::once())
                          ->method('createRefundPaymentForInvoice')
                          ->with($invoiceMock)
        ;

        $entityManagerMock->expects(self::once())
                          ->method('flush')
        ;

        $refundManagerMock->addRefundPaymentToInvoice($invoiceMock);
    }

    public function testCreateRefundPaymentForInvoiceThrowsExceptionWhenRemainingAmountIsZero(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(0)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to get a refund payment.');
        $refundManager->createRefundPaymentForInvoice($invoiceMock);
    }

    public function testCreateRefundPaymentForInvoiceThrowsExceptionWhenRemainingAmountIsNegative(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(-100)
        ;

        $refundManager = $this->getRefundManager();
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive remaining amount to get a refund payment.');
        $refundManager->createRefundPaymentForInvoice($invoiceMock);
    }

    public function testCreateRefundPaymentForInvoiceGetsDescriptionTranslation(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getRemainingAmount')
                    ->willReturn(100)
        ;
        $invoiceMock->method('getNumber')
                    ->willReturn('CO20240001')
        ;

        $mockTranslator = $this->createMock(TranslatorInterface::class);
        $mockTranslator->method('trans')
                       ->willReturn('description')
        ;

        $mockTranslator->expects(self::once())
                       ->method('trans')
                       ->with('invoice.description.cancel', ['%invoiceNumber%' => $invoiceMock->getNumber()], 'invoice')
        ;

        $refundManager = $this->getRefundManager(null, $mockTranslator);
        $refundManager->createRefundPaymentForInvoice($invoiceMock);
    }

    public function testCreateRefundPaymentForInvoiceCreatesRefundPaymentOfTypeRefund(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $refundManager = $this->getRefundManager();
        $refundPayment = $refundManager->createRefundPaymentForInvoice($invoice);

        self::assertSame(Payment::PAYMENT_TYPE_REFUND, $refundPayment->getType());
    }

    public function testCreateRefundPaymentForInvoiceCreatesRefundPaymentWithRemainingAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $partialPayment = new Payment();
        $partialPayment->setAmount(50)
        ->setType(Payment::PAYMENT_TYPE_TRANSACTION);
        $invoice->addPayment($partialPayment);

        $refundManager = $this->getRefundManager();
        $refundPayment = $refundManager->createRefundPaymentForInvoice($invoice);

        self::assertSame(50, $refundPayment->getAmount());
    }

    public function testCreateRefundPaymentForInvoiceCreatesRefundPaymentWithOriginalInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $partialPayment = new Payment();
        $partialPayment->setAmount(50)
            ->setType(Payment::PAYMENT_TYPE_TRANSACTION);

        $invoice->addPayment($partialPayment);

        $refundManager = $this->getRefundManager();
        $refundPayment = $refundManager->createRefundPaymentForInvoice($invoice);

        self::assertSame($invoice, $refundPayment->getInvoice());
    }

    public function testCreateRefundPaymentForInvoiceCreatesRefundPaymentHasCurrentDate(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $partialPayment = new Payment();
        $partialPayment->setAmount(50)
            ->setType(Payment::PAYMENT_TYPE_TRANSACTION);
        $invoice->addPayment($partialPayment);

        $refundManager = $this->getRefundManager();
        $refundPayment = $refundManager->createRefundPaymentForInvoice($invoice);

        $now = new \DateTimeImmutable();
        self::assertSame($now->format('Ymd'), $refundPayment->getDate()->format('Ymd'));
    }

    public function testCreateRefundPaymentForInvoiceCreatesRefundPaymentWithTranslatedDescriptionInComment(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $partialPayment = new Payment();
        $partialPayment->setAmount(50)
                       ->setType(Payment::PAYMENT_TYPE_TRANSACTION);

        $invoice->addPayment($partialPayment);

        $mockTranslator = $this->createMock(TranslatorInterface::class);
        $mockTranslator->method('trans')
                       ->willReturn('description')
        ;

        $mockTranslator->expects(self::once())
                       ->method('trans')
                       ->with('invoice.description.cancel', ['%invoiceNumber%' => $invoice->getNumber()], 'invoice')
        ;

        $refundManager = $this->getRefundManager(null, $mockTranslator);
        $refundPayment = $refundManager->createRefundPaymentForInvoice($invoice);

        self::assertSame('description', $refundPayment->getComment());
    }

    private function getRefundManager( ?VoucherManager $voucherManager = null, ?TranslatorInterface $translator = null, ?EntityManagerInterface $entityManager = null, ?InvoiceManager $invoiceManager = null,
    ): RefundManager {
        if (null === $voucherManager) {
            $voucherManager = $this->createMock(VoucherManager::class);
        }

        if (null === $translator) {
            $translator = $this->createMock(TranslatorInterface::class);
        }

        if (null === $entityManager) {
            $entityManager = $this->createMock(EntityManagerInterface::class);
        }

        if (null === $invoiceManager) {
            $invoiceManager = $this->createMock(InvoiceManager::class);
        }

        return new RefundManager($voucherManager, $translator, $entityManager, $invoiceManager);
    }
}
