<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Admin\BookingCrudController;
use App\Entity\Booking;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class AdminMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly string $supportEmail
    ) {
    }

    public function notifyAdminAboutBooking(Booking $booking): void
    {
        if (null === $booking->getBusinessDay() || null === $booking->getBusinessDay()->getDate()) {
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
            'text' => sprintf(
                'Es wurde eine neue Buchung für den %s getätigt',
                $booking->getBusinessDay()->getDate()->format('d/m/Y')
            ),
            'link' => $link,
        ];

        $this->sendEmail($subject, $context);
    }

    public function notifyAdminAboutBookingCancellation(\DateTimeInterface $bookingDate): void
    {
        $subject = 'Buchung cancelled : ' . $bookingDate->format('d/m/Y');
        $context = [
            'text' => sprintf(
                'Es wurde eine neue Buchung gecancelt. Buchungsdatum: %s',
                $bookingDate->format('d/m/Y')
            ),
        ];

        $this->sendEmail($subject, $context);
    }

    /**
     * @param array <string, mixed> $context
     */
    private function sendEmail(string $subject, array $context): void
    {
        $email = (new TemplatedEmail())
            ->to($this->supportEmail)
            ->subject($subject)
            ->htmlTemplate('admin/email/admin_notification.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
