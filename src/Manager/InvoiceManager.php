<?php

declare(strict_types = 1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use App\Trait\EmailContextTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly InvoiceRepository $invoiceRepository,
        private string $invoicePrefix,
        private string $documentVaultEmail
    ) {
    }

    public function createInvoiceFromBooking(Booking $booking, Price $price): Invoice
    {
        if (null === $booking->getUser()) {
            throw new \InvalidArgumentException('Booking must have a user');
        }

        if (null !== $booking->getInvoice()) {
            return $booking->getInvoice();
        }

        $invoiceNumber = $this->getInvoiceNumber();

        $invoice = new Invoice();
        $invoice->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($price->getAmount())
                ->setNumber($invoiceNumber)
                ->setDate($this->now())
        ;

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    public function generateBookingInvoicePdf(Invoice $invoice): void
    {
        $this->invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function sendBookingInvoicePerEmail(Invoice $invoice): void
    {
        if (null === $invoice->getUser()) {
            throw new \InvalidArgumentException('Invoice must have a user');
        }

        if (null === $invoice->getUser()->getEmail()) {
            throw new \InvalidArgumentException('User must have an email');
        }

        $uuid = $invoice->getBookings()->first()->getUuid();
        $link = $this->urlGenerator->generate('booking_payment_paypal', ['uuid' => $uuid],
            UrlGeneratorInterface::ABSOLUTE_URL);

        $subject                        = $this->translator->trans('booking.invoice.email.subject');
        $salutation                     = $this->translator->trans('booking.invoice.email.salutation', [
            '%firstName%' => $invoice->getUser()->getFirstName(),
        ]);
        $context                        = $this->getStandardEmailContext($this->translator, 'booking.invoice.email');
        $context['texts']['salutation'] = $salutation;
        $context['link']                = $link;

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $this->sendEmailToUser($invoice, $subject, $context, $invoicePath);
        $this->sendInvoiceToDocumentVault($invoice, $invoicePath);
    }

    public function createVoucherInvoice(User $user, Price $price, Collection $vouchers): Invoice
    {
        $invoiceNumber = $this->getInvoiceNumber();

        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount($price->getAmount())
                ->setNumber($invoiceNumber)
                ->setDate($this->now())
                ->setVouchers($vouchers)
        ;

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    public function generateVoucherInvoicePdf(Invoice $invoice, Price $voucherPrice): void
    {
        $this->invoiceGenerator->generateVoucherInvoice($invoice, $voucherPrice);
    }

    public function sendVoucherInvoicePerEmail(Invoice $invoice, Price $voucherPrice): void
    {
        if (null === $invoice->getUser()) {
            throw new \InvalidArgumentException('Invoice must have a user');
        }

        if (null === $invoice->getUser()->getEmail()) {
            throw new \InvalidArgumentException('User must have an email');
        }

        $link = $this->urlGenerator->generate(
            'voucher_payment_paypal',
            ['voucherPriceId' => $voucherPrice->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $subject                        = $this->translator->trans('voucher.invoice.email.subject');
        $salutation                     = $this->translator->trans('booking.invoice.email.salutation', [
            '%firstName%' => $invoice->getUser()->getFirstName(),
        ]);
        $context                        = $this->getStandardEmailContext($this->translator, 'voucher.invoice.email');
        $context['texts']['salutation'] = $salutation;
        $context['link']                = $link;

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $this->sendEmailToUser($invoice, $subject, $context, $invoicePath);
        $this->sendInvoiceToDocumentVault($invoice, $invoicePath);
    }

    public function getInvoiceNumber(): string
    {
        $year        = $this->now()->format('Y');
        $lastInvoice = $this->invoiceRepository->findLatestInvoiceForYear($year);

        if (null === $lastInvoice) {

            return $this->invoicePrefix . $year . '0001';
        }

        $invoiceNumberElements = explode($this->invoicePrefix, $lastInvoice->getNumber());
        $number                = (int)$invoiceNumberElements[1];

        return $this->invoicePrefix . $number + 1;
    }

    private function sendEmailToUser(Invoice $invoice, $subject, $context, $invoicePath)
    {
        $email = (new TemplatedEmail())
            ->to($invoice->getUser()->getEmail())
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
    }

    private function sendInvoiceToDocumentVault(Invoice $invoice, string $invoicePath): void
    {
        $email = (new Email())
            ->to($this->documentVaultEmail)
            ->subject('Invoice ' . $invoice->getNumber())
            ->attachFromPath($invoicePath);

        $this->mailer->send($email);
    }
}
