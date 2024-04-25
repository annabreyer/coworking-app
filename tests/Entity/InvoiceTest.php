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
        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(50);

        self::assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100);
        $invoice->addPayment($payment);

        self::assertTrue($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsExceedTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100);
        $payment2 = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment2->setAmount(50);

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
        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(100);

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $transactionPayment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $transactionPayment->setAmount(50);

        $voucherPayment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $voucherPayment->setAmount(50);

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsTrueWhenPaymentIsVoucher(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100);

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
        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100);

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $transactionPayment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $transactionPayment->setAmount(50);

        $voucherPayment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $voucherPayment->setAmount(50);

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsTrueWhenPaymentIsTransaction(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount(100);

        self::assertTrue($invoice->isFullyPaidByTransaction());
    }
}
