<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class PaymentManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly VoucherManager $voucherManager,
    ) {
    }

    public function createVoucherPayment(Voucher $voucher): Payment
    {
        if (null === $voucher->getValue() || 0 >= $voucher->getValue()) {
            throw new \LogicException('Voucher must have a positive value to be used as payment.');
        }

        $payment = new Payment();
        $payment
            ->setAmount($voucher->getValue())
            ->setDate($this->now())
            ->setVoucher($voucher)
            ->setType(Payment::PAYMENT_TYPE_VOUCHER)
        ;

        return $payment;
    }

    public function savePayment(Payment $payment): void
    {
        if (null === $payment->getid()) {
            $this->entityManager->persist($payment);
        }

        $this->entityManager->flush();
    }

    public function handleInvoicePaymentWithVoucher(Invoice $invoice, Voucher $voucher): void
    {
        if (null === $invoice->getAmount() || 0 >= $invoice->getAmount()) {
            throw new \InvalidArgumentException('Invoice must have a positive amount to be paid.');
        }

        if (null === $voucher->getValue() || 0 >= $voucher->getValue()) {
            throw new \InvalidArgumentException('Voucher must have a positive value in order to be used as payment.');
        }

        $payment = $this->createVoucherPayment($voucher);
        $this->savePayment($payment);

        $invoice->addPayment($payment);

        $this->voucherManager->useVoucher($voucher);
        $this->invoiceManager->reduceInvoiceAmount($invoice, $voucher->getValue());

        $this->entityManager->flush();
    }

    public function finalizePaypalPayment(Invoice $invoice): void
    {
        $invoiceAmount = $invoice->getAmount();
        if (null === $invoiceAmount) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $payment = new Payment();
        $payment
            ->setInvoice($invoice)
            ->setAmount($invoiceAmount)
            ->setDate($this->now())
            ->setType(Payment::PAYMENT_TYPE_PAYPAL);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }
}
