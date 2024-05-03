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

        static::assertTrue($voucher->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiryDateIsInTheFuture(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        static::assertFalse($voucher->isExpired());
    }

    public function testHasBeenPaidReturnsTrueWhenAPaymentIsAttachedToTheAttachedInvoice(): void
    {
        $voucher = new Voucher();
        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(20);

        $voucher->setInvoice($invoice);

        static::assertTrue($voucher->hasBeenPaid());
    }

    public function testHasBeenPaidReturnsFalseWhenAmountOnlyPartlyPaid(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(10);

        $invoice->addPayment($payment);

        $voucher = new Voucher();
        $voucher->setInvoice($invoice);

        static::assertFalse($voucher->hasBeenPaid());
    }

    public function testIsValidReturnsFalseWhenExpired(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('yesterday'));

        static::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenUseDateIsSet(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setUseDate(new \DateTimeImmutable('yesterday'));

        static::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenNoPaymentIsAttached(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $voucher->setInvoice($invoice);

        static::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenAttachedPaymentsDoNotCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(50);

        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setInvoice($invoice);

        static::assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsTrueWhenNotExpiredAndNoUseDateAndPaymentsCoverTheAmount(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(20);

        $voucher->setInvoice($invoice);

        static::assertTrue($voucher->isValid());
    }
}
