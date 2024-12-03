<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use PHPUnit\Framework\TestCase;

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
        $payment = new Payment();
        $payment
            ->setAmount(50)
            ->setInvoice($invoice)
            ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

        self::assertFalse($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsCoverTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;
        $invoice->addPayment($payment);

        self::assertTrue($invoice->isFullyPaid());
    }

    public function testIsFullyPaidReturnsTrueWhenPaymentsExceedTheAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;
        $payment2 = new Payment();
        $payment2->setAmount(50)
                 ->setInvoice($invoice)
                 ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

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
        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_TRANSACTION)
        ;

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);
        $transactionPayment = new Payment();
        $transactionPayment->setAmount(50)
                           ->setInvoice($invoice)
                           ->setType(Payment::PAYMENT_TYPE_PAYPAL)
        ;

        $voucherPayment = new Payment();
        $voucherPayment->setAmount(50)
                       ->setInvoice($invoice)
                       ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

        self::assertFalse($invoice->isFullyPaidByVoucher());
    }

    public function testIsFullyPaidByVoucherReturnsTrueWhenPaymentIsVoucher(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

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
        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $transactionPayment = new Payment();
        $transactionPayment->setAmount(50)
                           ->setInvoice($invoice)
                           ->setType(Payment::PAYMENT_TYPE_TRANSACTION)
        ;

        $voucherPayment = new Payment();
        $voucherPayment->setAmount(50)
                       ->setInvoice($invoice)
                       ->setType(Payment::PAYMENT_TYPE_PAYPAL)
        ;

        self::assertFalse($invoice->isFullyPaidByTransaction());
    }

    public function testIsFullyPaidByTransactionReturnsTrueWhenPaymentIsTransaction(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_TRANSACTION)
        ;

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
        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

        self::assertFalse($invoice->isFullyPaidByPayPal());
    }

    public function testIsFullyPaidByPayPalReturnsFalseWhenPaymentsAreMixed(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $transactionPayment = new Payment();
        $transactionPayment->setAmount(50)
                           ->setInvoice($invoice)
                           ->setType(Payment::PAYMENT_TYPE_TRANSACTION)
        ;

        $voucherPayment = new Payment();
        $voucherPayment->setAmount(50)
                       ->setInvoice($invoice)
        ;

        self::assertFalse($invoice->isFullyPaidByPayPal());
    }

    public function testIsFullyPaidByPayPalReturnsTrueWhenPaymentIsPayPal(): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(100);

        $payment = new Payment();
        $payment->setAmount(100)
                ->setInvoice($invoice)
                ->setType(Payment::PAYMENT_TYPE_PAYPAL)
        ;

        self::assertTrue($invoice->isFullyPaidByPayPal());
    }

    public function testGetPaidAmountReturnsZeroWhenThereIsNoPayment(): void
    {
        $invoice = new Invoice();
        self::assertSame(0, $invoice->getPaidAmount());
    }

    public function testGetPaidAmountReturnsSumOfPayments(): void
    {
        $invoice = new Invoice();
        $payment = new Payment();
        $payment->setAmount(100);
        $payment->setType(Payment::PAYMENT_TYPE_TRANSACTION);

        $invoice->addPayment($payment);
        $secondPayment = new Payment();
        $secondPayment->setAmount(200);
        $secondPayment->setType(Payment::PAYMENT_TYPE_VOUCHER);

        $invoice->addPayment($secondPayment);

        self::assertSame(300, $invoice->getPaidAmount());
    }

    public function testGetRemainingAmountReturnsZeroWhenThereIsNoPayment(): void
    {
        $invoice = new Invoice();
        self::assertSame(0, $invoice->getRemainingAmount());
    }

    public function testGetRemainingAmountReturnsAmountMinusSumOfPayments(): void
    {
        $invoice = new Invoice();
        $payment = new Payment();
        $payment->setAmount(100);
        $payment->setType(Payment::PAYMENT_TYPE_TRANSACTION);

        $invoice->addPayment($payment);
        $invoice->setAmount(200);

        self::assertSame(100, $invoice->getRemainingAmount());
    }
}
