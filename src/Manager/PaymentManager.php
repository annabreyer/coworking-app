<?php declare(strict_types = 1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class PaymentManager
{
    use ClockAwareTrait;
    public function __construct(private readonly EntityManagerInterface $entityManager,) {
    }

    public function handleVoucherPayment(Invoice $invoice, Voucher $voucher): void
    {
        $payment = new Payment();
        $payment
            ->setInvoice($invoice)
            ->setAmount($voucher->getValue())
            ->setType(Payment::PAYMENT_TYPE_VOUCHER)
            ->setDate($this->now())
            ->setVoucher($voucher)
        ;

        $voucher->setUseDate($this->now());
        $invoice->addPayment($payment);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }
}
