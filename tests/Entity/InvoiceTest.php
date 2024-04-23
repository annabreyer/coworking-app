<?php declare(strict_types = 1);

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use Monolog\Test\TestCase;

class InvoiceTest extends TestCase
{


    public function testIsAlreadyPaidReturnsFalseWhenThereAreNoPayments(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $this->assertFalse($invoice->isAlreadyPaid());
    }

    public function testIsAlreadyPaidReturnsFalseWhenPaymentsDoNotCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment();
        $payment->setAmount(50);
        $invoice->addPayment($payment);

        $this->assertFalse($invoice->isAlreadyPaid());
    }

    public function testIsAlreadyPaidReturnsTrueWhenPaymentsCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment();
        $payment->setAmount(100);
        $invoice->addPayment($payment);

        $this->assertTrue($invoice->isAlreadyPaid());
    }

    public function testIsAlreadyPaidReturnsTrueWhenPaymentsExceedTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment();
        $payment->setAmount(100);
        $invoice->addPayment($payment);
        $payment = new Payment();
        $payment->setAmount(50);
        $invoice->addPayment($payment);

        $this->assertTrue($invoice->isAlreadyPaid());
    }
}