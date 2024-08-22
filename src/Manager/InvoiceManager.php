<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceManager
{
    use ClockAwareTrait;
    use EmailContextTrait;

    /**
     * @param non-empty-string $invoicePrefix
     */
    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly Filesystem $filesystem,
        private readonly string $invoicePrefix,
    ) {
    }

    public static function getClientNumber(int $userId): string
    {
        $number = (string) $userId;

        return str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    public function getInvoiceNumber(): string
    {
        $year        = $this->now()->format('Y');
        $lastInvoice = $this->invoiceRepository->findLatestInvoiceForYear($year);

        if (null === $lastInvoice || null === $lastInvoice->getNumber()) {
            return $this->invoicePrefix . $year . '0001';
        }

        $invoiceNumberElements = explode($this->invoicePrefix, $lastInvoice->getNumber());
        $number                = (int) $invoiceNumberElements[1];
        $newNumber             = $number + 1;

        return $this->invoicePrefix . $newNumber;
    }

    public function createInvoice(User $user, int $amount, bool $save = true): Invoice
    {
        $invoiceNumber = $this->getInvoiceNumber();

        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount($amount)
                ->setNumber($invoiceNumber)
                ->setDate($this->now())
        ;

        if (true === $save) {
            $this->saveInvoice($invoice);
        }

        return $invoice;
    }

    public function saveInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getid()) {
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();
    }

    public function cancelUnpaidInvoice(Invoice $invoice): void
    {
        if (true === $invoice->isFullyPaid()) {
            throw new \InvalidArgumentException('Invoice is already fully paid.');
        }

        $user = $invoice->getUser();
        if (null === $user) {
            throw new \InvalidArgumentException('Invoice must have a user.');
        }

        $description = $this->translator->trans('invoice.description.cancel', ['%invoiceNumber%' => $invoice->getNumber()], 'invoice');

        $originalInvoicePayment = new Payment(Payment::PAYMENT_TYPE_REFUND);
        $originalInvoicePayment->setAmount((int) $invoice->getAmount())
                               ->setDate($this->now())
                               ->setInvoice($invoice)
                               ->setComment($description);
        $invoice->addPayment($originalInvoicePayment);
        $this->entityManager->flush();

        $cancelledAmount = $invoice->getAmount() * -1;
        $refundInvoice   = $this->createInvoice($user, $cancelledAmount, false);
        $refundInvoice->setDescription($description);

        $this->saveInvoice($refundInvoice);
        $this->generateGeneralInvoicePdf($refundInvoice);
    }

    public function createInvoiceFromBooking(Booking $booking, int $amount, bool $save = true): Invoice
    {
        if (null === $booking->getUser()) {
            throw new \InvalidArgumentException('Booking must have a user.');
        }

        if (null !== $booking->getInvoice()) {
            return $booking->getInvoice();
        }

        $invoiceNumber = $this->getInvoiceNumber();
        $invoice       = new Invoice();
        $invoice
            ->addBooking($booking)
            ->setUser($booking->getUser())
            ->setAmount($amount)
            ->setNumber($invoiceNumber)
            ->setDate($this->now())
        ;

        if (true === $save) {
            $this->saveInvoice($invoice);
        }

        return $invoice;
    }

    public function generateInvoicePdf(Invoice $invoice): void
    {
        if ($invoice->isBookingInvoice()) {
            $this->generateBookingInvoicePdf($invoice);
        } elseif ($invoice->isVoucherInvoice()) {
            $this->generateVoucherInvoicePdf($invoice);
        } else {
            $this->generateGeneralInvoicePdf($invoice);
        }
    }

    public function generateBookingInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function generateVoucherInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateVoucherInvoice($invoice);
    }

    public function generateGeneralInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateGeneralInvoice($invoice);
    }

    public function regenerateInvoicePdf(Invoice $invoice): void
    {
        if (null !== $invoice->getFilePath()) {
            $this->filesystem->remove($invoice->getFilePath());
        }

        $this->generateInvoicePdf($invoice);
    }
}
