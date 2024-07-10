<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use Monolog\Test\TestCase;

class InvoiceTest extends TestCase
{
    public function testIsFullyPaidReturnsFalseWhenThereAreNoPayments(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        self::assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsFalseWhenPaymentsDoNotCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $payment
            ->setAmount(50)
            ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100)
            ->setInvoice($invoice);
        $invoice->addPayment($payment);

        self::assertTrue($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsExceedTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment( Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100)
            ->setInvoice($invoice);
        $payment2 = new Payment( Payment::PAYMENT_TYPE_VOUCHER);
        $payment2->setAmount(50)
            ->setInvoice($invoice);

        self::assertTrue($invoice->isFullyPaid());
    }

    public function testIsFullyPaidByVoucherReturnsFalseWhenThereAreNoPayments(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsFalseWhenPaymentIsTransaction(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(100)
            ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $transactionPayment = new Payment(Payment::PAYMENT_TYPE_PAYPAL);
        $transactionPayment->setAmount(50)
            ->setInvoice($invoice);

        $voucherPayment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $voucherPayment->setAmount(50)
            ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsTrueWhenPaymentIsVoucher(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment( Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100)
            ->setInvoice($invoice);

        self::assertTrue($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByTransactionReturnsFalseWhenThereAreNoPayments(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsFalseWhenPaymentIsVoucher(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100)
            ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $transactionPayment = new Payment( Payment::PAYMENT_TYPE_TRANSACTION);
        $transactionPayment->setAmount(50)
        ->setInvoice($invoice);

        $voucherPayment = new Payment(Payment::PAYMENT_TYPE_PAYPAL);
        $voucherPayment->setAmount(50)
        ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsTrueWhenPaymentIsTransaction(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(100)
        ->setInvoice($invoice);

        self::assertTrue($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByPayPalReturnsFalseWhenThereAreNoPayments(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        self::assertFalse($invoice->isFullyPaidByPayPal());
    }

    public function testIsFullyPaidByPayPalReturnsFalseWhenPaymentIsTransaction(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100)
        ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByPayPal());
    }

    public function testIsFullyPaidByPayPalReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $transactionPayment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $transactionPayment->setAmount(50)
        ->setInvoice($invoice);

        $voucherPayment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $voucherPayment->setAmount(50)
        ->setInvoice($invoice);

        self::assertFalse($invoice->isFullyPaidByPayPal());
    }

    public function testIsFullyPaidByPayPalReturnsTrueWhenPaymentIsPayPal(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment(Payment::PAYMENT_TYPE_PAYPAL);
        $payment->setAmount(100)
        ->setInvoice($invoice);

        self::assertTrue($invoice->isFullyPaidByPayPal());
    }
}
