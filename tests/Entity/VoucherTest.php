<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class VoucherTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testIsExpiredReturnsTrueWhenExpiryDateIsInThePast(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('yesterday'));

        self::assertTrue($voucher->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiryDateIsInTheFuture(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        self::assertFalse($voucher->isExpired());
    }

    public function testHasBeenPaidReturnsTrueWhenAPaymentIsAttachedToTheAttachedInvoice(): void
    {
        $voucher = new Voucher();
        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(20)
                ->setInvoice($invoice)
        ;

        $voucher->setInvoice($invoice);

        self::assertTrue($voucher->isFullyPaid());
    }

    public function testHasBeenPaidReturnsFalseWhenAmountOnlyPartlyPaid(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(10)
                ->setInvoice($invoice)
        ;

        $invoice->addPayment($payment);

        $voucher = new Voucher();
        $voucher->setInvoice($invoice);

        self::assertFalse($voucher->isFullyPaid());
    }

    public function testIsValidReturnsFalseWhenExpired(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('yesterday'));

        self::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenUseDateIsSet(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setUseDate(new \DateTimeImmutable('yesterday'));

        self::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenAmountIsSuperiorToZeroNoPaymentIsAttached(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $invoice->setAmount(1355);
        $voucher->setInvoice($invoice);

        self::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsTrueWhenAmountIsZeroAndNoPaymentIsAttached(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $invoice->setAmount(0);
        $invoice->addVoucher($voucher);

        self::assertTrue($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenAttachedPaymentsDoNotCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(50)
                ->setInvoice($invoice)
        ;

        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setInvoice($invoice);

        self::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsTrueWhenNotExpiredAndNoUseDateAndPaymentsCoverTheAmount(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(20)
                ->setInvoice($invoice)
        ;

        $voucher->setInvoice($invoice);

        self::assertTrue($voucher->isValid());
    }
}
