<?php

declare(strict_types=1);

namespace App\EventSubscriber\Admin;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Manager\InvoiceManager;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class VoucherPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(private InvoiceManager $invoiceManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => ['handleVoucherPayment'],
            BeforeEntityUpdatedEvent::class   => ['handleVoucherPayment'],
        ];
    }

    public function handleVoucherPayment(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Payment) {
            $this->setVoucherUsed($entity);
            $this->setInvoiceAmountToZero($entity->getInvoice());
            $this->regenerateInvoice($entity->getInvoice());
        }

        if ($entity instanceof Invoice) {
            foreach ($entity->getPayments() as $payment) {
                $this->setVoucherUsed($payment);
            }

            $this->setInvoiceAmountToZero($entity);
            $this->regenerateInvoice($entity);
        }
    }

    private function setVoucherUsed(Payment $payment): void
    {
        if (null === $payment->getVoucher()) {
            return;
        }

        $voucher = $payment->getVoucher();
        $voucher->setUseDate($payment->getDate());
    }

    private function setInvoiceAmountToZero(Invoice $invoice): void
    {
        if (false === $invoice->isFullyPaidByVoucher()) {
            return;
        }

        $invoice->setAmount(0);
    }

    private function regenerateInvoice(Invoice $invoice): void
    {
        if (false === $invoice->isFullyPaidByVoucher()) {
            return;
        }

        $this->invoiceManager->regenerateInvoicePdf($invoice);
    }
}
