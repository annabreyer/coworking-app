<?php

declare(strict_types = 1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly InvoiceRepository $invoiceRepository,
        private string $invoicePrefix
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

        $link = $this->urlGenerator->generate(
            'booking_payment_paypal',
            ['booking' => $invoice->getBookings()->first()->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $subject = $this->translator->trans('booking.invoice.email.subject');
        $context = [
            'texts' => [
                'salutation'   => $this->translator->trans(
                    'booking.invoice.email.salutation',
                    ['%firstName%' => $invoice->getUser()->getFirstName()]
                ),
                'instructions' => $this->translator->trans('booking.invoice.email.instruction'),
                'explanation'  => $this->translator->trans('booking.invoice.email.explanation'),
                'signature'    => $this->translator->trans('booking.invoice.email.signature'),
            ],
            'link'  => $link,
        ];

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $email = (new TemplatedEmail())
            ->to($invoice->getUser()->getEmail())
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
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

        $subject = $this->translator->trans('voucher.invoice.email.subject');
        $context = [
            'texts' => [
                'salutation'   => $this->translator->trans(
                    'voucher.invoice.email.salutation',
                    ['%firstName%' => $invoice->getUser()->getFirstName()]
                ),
                'instructions' => $this->translator->trans('voucher.invoice.email.instruction'),
                'explanation'  => $this->translator->trans('voucher.invoice.email.explanation'),
                'signature'    => $this->translator->trans('voucher.invoice.email.signature'),
            ],
            'link'  => $link,
        ];

        $invoicePath = $this->invoiceGenerator->getTargetDirectory($invoice);
        $invoicePath .= '/' . $invoice->getNumber() . '.pdf';

        $email = (new TemplatedEmail())
            ->to($invoice->getUser()->getEmail())
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
    }

    public function getInvoiceNumber(): string
    {
        $year         = $this->now()->format('Y');
        $lastInvoice  = $this->invoiceRepository->findLatestInvoiceForYear($year);

        if (null === $lastInvoice) {

            return $this->invoicePrefix . $year . '0001';
        }

        $invoiceNumberElements = explode($this->invoicePrefix, $lastInvoice->getNumber());
        $number                = (int)$invoiceNumberElements[1];

        return $this->invoicePrefix . $number + 1;
    }
}
