<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Service\InvoiceGenerator;
use App\Trait\EmailContextTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceMailerManager
{
    use EmailContextTrait;

    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Filesystem $filesystem,
        private readonly string $documentVaultEmail,
        private readonly string $env
    ) {
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
     * @throws TransportExceptionInterface
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
