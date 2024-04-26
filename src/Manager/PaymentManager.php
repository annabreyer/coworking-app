<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use App\Service\AdminMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class PaymentManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminMailerService $adminMailer,
    ) {
    }

    public function handleVoucherPayment(Invoice $invoice, Voucher $voucher): void
    {
        if (null === $invoice->getAmount()) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        if (null === $voucher->getValue()) {
            throw new \InvalidArgumentException('Voucher must have a value.');
        }

        $invoiceAmount = $invoice->getAmount() - $voucher->getValue();
        $payment       = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment
            ->setAmount($voucher->getValue())
            ->setDate($this->now())
            ->setVoucher($voucher)
        ;

        $voucher->setUseDate($this->now());
        $invoice->setAmount($invoiceAmount);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        if (0 > $invoiceAmount) {
            $this->adminMailer->notifyAdminAboutNegativeInvoice($invoice);
        }
    }
}
