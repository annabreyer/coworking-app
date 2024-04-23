<?php declare(strict_types = 1);

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

        $this->assertTrue($voucher->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiryDateIsInTheFuture(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $this->assertFalse($voucher->isExpired());
    }

    public function testHasBeenPaidReturnsTrueWhenAPaymentIsAttachedToTheAttachedInvoice(): void
    {
        $voucher = new Voucher();
        $invoice = new Invoice();
        $invoice->setAmount(20);
        $payment = new Payment();
        $payment->setAmount(20);

        $invoice->addPayment($payment);
        $voucher->setInvoice($invoice);

        $this->assertTrue($voucher->hasBeenPaid());
    }


    public function testHasBeenPaidReturnsFalseWhenAmountOnlyPartlyPaid(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment();
        $payment->setAmount(10);

        $invoice->addPayment($payment);

        $voucher = new Voucher();
        $voucher->setInvoice($invoice);

        $this->assertFalse($voucher->hasBeenPaid());
    }

    public function testIsValidReturnsFalseWhenExpired(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('yesterday'));

        $this->assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenUseDateIsSet(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setUseDate(new \DateTimeImmutable('yesterday'));

        $this->assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsFalseWhenNoPaymentIsAttached(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $voucher->setInvoice($invoice);

        $this->assertFalse($voucher->isValid());
    }


    public function testIsValidReturnsFalseWhenAttachedPaymentsDoNotCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment();
        $payment->setAmount(50);
        $invoice->addPayment($payment);

        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher->setInvoice($invoice);

        $this->assertFalse($voucher->isValid());
    }

    public function testIsValidReturnsTrueWhenNotExpiredAndNoUseDateAndPaymentsCoverTheAmount(): void
    {
        $voucher = new Voucher();
        $voucher->setExpiryDate(new \DateTimeImmutable('tomorrow'));

        $invoice = new Invoice();
        $invoice->setAmount(20);

        $payment = new Payment();
        $payment->setAmount(20);

        $invoice->addPayment($payment);

        $voucher->setInvoice($invoice);

        $this->assertTrue($voucher->isValid());
    }
}