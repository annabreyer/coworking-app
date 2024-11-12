<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Admin\BookingCrudController;
use App\Controller\Admin\InvoiceCrudController;
use App\Entity\Booking;
use App\Entity\Invoice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AdminMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly string $supportEmail,
        private readonly string $documentVaultEmail,
        private readonly string $env,
    ) {
    }

    public function notifyAdminAboutBooking(Booking $booking): void
    {
        if (null === $booking->getBusinessDay()) {
            throw new \LogicException('Booking has no business day');
        }

        $link = $this->adminUrlGenerator
            ->setController(BookingCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($booking->getId())
            ->generateUrl()
        ;

        $subject = 'Neue Buchung am: ' . $booking->getBusinessDay()->getDate()->format('d/m/Y');
        $context = [
            'text' => \sprintf(
                'Es wurde eine neue Buchung für den %s getätigt',
                $booking->getBusinessDay()->getDate()->format('d/m/Y')
            ),
            'link' => $link,
        ];

        $this->sendEmailToSupport($subject, $context);
    }

    public function notifyAdminAboutBookingCancellation(\DateTimeInterface $bookingDate): void
    {
        $subject = 'Buchung cancelled : ' . $bookingDate->format('d/m/Y');
        $context = [
            'text' => \sprintf(
                'Es wurde eine neue Buchung gecancelt. Buchungsdatum: %s',
                $bookingDate->format('d/m/Y')
            ),
        ];

        $this->sendEmailToSupport($subject, $context);
    }

    public function notifyAdminAboutNegativeInvoice(Invoice $invoice): void
    {
        $link = $this->adminUrlGenerator
            ->setController(InvoiceCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoice->getId())
            ->generateUrl()
        ;

        $subject = 'Rechnung mit negativem Betrag: ' . $invoice->getNumber();
        $context = [
            'text' => \sprintf(
                'Die Rechnung mit der Nummer %s hat einen negativen Betrag',
                $invoice->getNumber()
            ),
            'link' => $link,
        ];

        $this->sendEmailToSupport($subject, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws TransportExceptionInterface
     */
    private function sendEmailToSupport(string $subject, array $context, ?string $attachmentPath = null): void
    {
        if (\in_array($this->env, ['dev', 'staging', 'test'], true)) {
            $subject = '[' . strtoupper($this->env) . '] ' . $subject;
        }

        $email = (new TemplatedEmail())
            ->to($this->supportEmail)
            ->subject($subject)
            ->htmlTemplate('admin/email/admin_notification.html.twig')
            ->context($context)
        ;

        if (null !== $attachmentPath) {
            $email->attachFromPath($attachmentPath);
        }

        $this->mailer->send($email);
    }

    public function sendInvoiceToDocumentVault(Invoice $invoice, string $invoicePath): void
    {
        if (\in_array($this->env, ['dev', 'staging'], true)) {
            return;
        }

        $email = (new Email())
            ->to($this->documentVaultEmail)
            ->subject('Invoice ' . $invoice->getNumber())
            ->text('Invoice ' . $invoice->getNumber())
            ->attachFromPath($invoicePath)
        ;

        $this->mailer->send($email);
    }
}
