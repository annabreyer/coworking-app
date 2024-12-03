<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\AdminMailerService;
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
        private readonly AdminMailerService $adminMailer,
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

    public function createInvoice(User $user, int $amount): Invoice
    {
        $invoiceNumber = $this->getInvoiceNumber();

        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount($amount)
                ->setNumber($invoiceNumber)
                ->setDate($this->now())
        ;

        return $invoice;
    }

    public function saveInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getid()) {
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();
    }

    public function reduceInvoiceAmount(Invoice $invoice, int $amount): void
    {
        $invoice->setAmount($invoice->getAmount() - $amount);
        $this->saveInvoice($invoice);

        if (0 > $invoice->getAmount()) {
            $this->adminMailer->notifyAdminAboutNegativeInvoice($invoice);
        }
    }

    public function createAndSaveInvoiceFromBooking(Booking $booking, int $amount): Invoice
    {
        if (null === $booking->getUser()) {
            throw new \InvalidArgumentException('Booking must have a user.');
        }

        if (null !== $booking->getInvoice()) {
            throw new \LogicException('Booking already has an invoice.');
        }

        $invoice = $this->createInvoice($booking->getUser(), $amount);
        $invoice->addBooking($booking);

        $this->saveInvoice($invoice);

        return $invoice;
    }

    public function createReversalInvoice(Invoice $invoice): Invoice
    {
        $user = $invoice->getUser();
        if (null === $user) {
            throw new \LogicException('Invoice must have a user.');
        }

        if (0 >= $invoice->getAmount()) {
            throw new \LogicException('Invoice must have a positive amount to generate a reversal invoice.');
        }

        $refundPayments = $invoice->getPayments()->filter(static function (Payment $payment) {
            return Payment::PAYMENT_TYPE_REFUND === $payment->getType();
        });

        if (1 !== \count($refundPayments)) {
            throw new \LogicException('Invoice must have exactly one refund payment to generate a reversal invoice.');
        }

        $cancelledAmount = -1 * $invoice->getAmount();
        $refundInvoice   = $this->createInvoice($user, $cancelledAmount);
        $refundInvoice->setDescription($invoice->getPayments()->first()->getComment());

        return $refundInvoice;
    }

    public function processReversalInvoice(Invoice $invoice): void
    {
        $refundInvoice = $this->createReversalInvoice($invoice);
        $this->saveInvoice($refundInvoice);
        $this->generateInvoicePdf($refundInvoice);
    }

    public function generateInvoicePdf(Invoice $invoice): void
    {
        if ($invoice->isBookingInvoice()) {
            $this->invoiceGenerator->generateBookingInvoice($invoice);
        } elseif ($invoice->isVoucherInvoice()) {
            $this->invoiceGenerator->generateVoucherInvoice($invoice);
        } else {
            $this->invoiceGenerator->generateGeneralInvoice($invoice);
        }
    }

    public function regenerateInvoicePdf(Invoice $invoice): void
    {
        if (null !== $invoice->getFilePath()) {
            $this->filesystem->remove($invoice->getFilePath());
        }

        $this->generateInvoicePdf($invoice);
    }
}
