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
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly Filesystem $filesystem,
        private readonly string $invoicePrefix,
        private readonly string $documentVaultEmail,
        private readonly string $env
    ) {
    }

    public static function getClientNumber(int $userId): string
    {
        $number = (string) $userId;
        $number = str_pad($number, 5, '0', STR_PAD_LEFT);

        return $number;
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

        $description     = $this->translator->trans('invoice.description.cancel', ['%invoiceNumber%' => $invoice->getNumber()], 'invoice');

        $originalInvoicePayment = new Payment(Payment::PAYMENT_TYPE_REFUND);
        $originalInvoicePayment->setAmount($invoice->getAmount())
                               ->setDate($this->now())
                               ->setInvoice($invoice)
                               ->setComment($description);
        $invoice->addPayment($originalInvoicePayment);
        $this->entityManager->flush();

        $cancelledAmount = $invoice->getAmount() * -1;
        $refundInvoice   = $this->createInvoice($invoice->getUser(), $cancelledAmount, false);
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

    public function generateBookingInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function sendBookingInvoiceToUser(Invoice $invoice): void
    {
        if (null === $invoice->getUser()) {
            throw new \InvalidArgumentException('Invoice must have a user.');
        }

        if (null === $invoice->getUser()->getEmail()) {
            throw new \InvalidArgumentException('User must have an email.');
        }

        if (false === $invoice->getBookings()->first()) {
            throw new \InvalidArgumentException('Invoice must have a booking.');
        }

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        if (false === $this->filesystem->exists($invoicePath)) {
            throw new \InvalidArgumentException('Invoice PDF does not exist.');
        }

        $link = $this->urlGenerator->generate(
            'invoice_payment_paypal',
            ['uuid' => $invoice->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $subject    = $this->translator->trans('booking.invoice.subject', [], 'email');
        $salutation = $this->translator->trans('booking.invoice.salutation', [
            '%firstName%' => $invoice->getUser()->getFirstName(),
        ], 'email');

        $context = [
            'link'  => $link,
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $salutation,
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans(
                    'booking.invoice.instructions',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION => $this->translator->trans(
                    'booking.invoice.explanation',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE => $this->translator->trans(
                    'booking.invoice.signature',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT     => $subject,
                self::EMAIL_STANDARD_ELEMENT_BUTTON_TEXT => $this->translator->trans(
                    'booking.invoice.button_text',
                    [],
                    'email'
                ),
            ],
        ];

        if ($invoice->isFullyPaid()) {
            $context['link']                 = '';
            $context['texts']['explanation'] = '';
        }

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $this->sendEmailToUser($invoice->getUser()->getEmail(), $subject, $context, $invoicePath);
    }

    public function generateVoucherInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateVoucherInvoice($invoice);
    }

    public function sendVoucherInvoiceToUser(Invoice $invoice): void
    {
        if (null === $invoice->getUser()) {
            throw new \InvalidArgumentException('Invoice must have a user');
        }

        if (null === $invoice->getUser()->getEmail()) {
            throw new \InvalidArgumentException('User must have an email');
        }

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        if (false === $this->filesystem->exists($invoicePath)) {
            throw new \InvalidArgumentException('Invoice PDF does not exist.');
        }

        $link = $this->urlGenerator->generate(
            'invoice_payment_paypal',
            ['uuid' => $invoice->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $subject    = $this->translator->trans('voucher.invoice.subject', [], 'email');
        $salutation = $this->translator->trans('voucher.invoice.salutation', [
            '%firstName%' => $invoice->getUser()->getFirstName(),
        ], 'email');
        $context = [
            'link'  => $link,
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $salutation,
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans(
                    'voucher.invoice.instructions',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION => $this->translator->trans(
                    'voucher.invoice.explanation',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE => $this->translator->trans(
                    'voucher.invoice.signature',
                    [],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT     => $subject,
                self::EMAIL_STANDARD_ELEMENT_BUTTON_TEXT => $this->translator->trans(
                    'voucher.invoice.button_text',
                    [],
                    'email'
                ),
            ],
        ];

        if ($invoice->isFullyPaid()) {
            $context['link']                 = '';
            $context['texts']['explanation'] = '';
        }

        $this->sendEmailToUser($invoice->getUser()->getEmail(), $subject, $context, $invoicePath);
    }

    public function generateGeneralInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateGeneralInvoice($invoice);
    }

    public function sendInvoiceToDocumentVault(Invoice $invoice): void
    {
        if (\in_array($this->env, ['dev', 'staging'], true)) {
            return;
        }

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $email = (new Email())
            ->to($this->documentVaultEmail)
            ->subject('Invoice ' . $invoice->getNumber())
            ->text('Invoice ' . $invoice->getNumber())
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    private function sendEmailToUser(string $userEmail, string $subject, array $context, string $invoicePath): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($userEmail))
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
    }
}
