<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Trait\EmailContextTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingMailerService
{
    use EmailContextTrait;

    public function __construct(
        private readonly UserMailerService $userMailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendBookingCancelledEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        if (null === $user) {
            throw new \LogicException('Booking must have a user to send the booking cancelled email.');
        }

        $userEmail = $user->getEmail();
        if (null === $userEmail) {
            throw new \LogicException('User must have an email to send the booking cancelled email.');
        }

        $bookingDate = $booking->getBusinessDay()?->getDate()?->format('d.m.Y');
        if (null === $bookingDate) {
            throw new \LogicException('Booking must have a date to send the booking cancelled email.');
        }

        $bookingRoom = $booking->getRoom()?->getName();
        if (null === $bookingRoom) {
            throw new \LogicException('Booking must have a room to send the booking cancelled email.');
        }

        $link       = $this->urlGenerator->generate('booking_step_date', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $subject    = $this->translator->trans('booking.cancel.subject', [], 'email');
        $salutation = $this->translator->trans('booking.cancel.salutation', ['%firstName%' => $user->getFirstName()], 'email');

        $context = [
            'link'  => $link,
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $salutation,
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans('booking.cancel.instructions', [
                    '%date%' => $bookingDate,
                    '%room%' => $bookingRoom,
                ], 'email'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION => $this->translator->trans('booking.cancel.explanation', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE   => $this->translator->trans('booking.cancel.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT     => $subject,
                self::EMAIL_STANDARD_ELEMENT_BUTTON_TEXT => $this->translator->trans('booking.cancel.button_text', [], 'email'),
            ],
        ];

        $this->userMailer->sendTemplatedEmail($userEmail, $subject, $context);
    }

    public function sendFirstBookingEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        if (null === $user) {
            throw new \LogicException('Booking must have a user to send the welcome email.');
        }

        $userEmail = $user->getEmail();
        if (null === $userEmail) {
            throw new \LogicException('User must have an email to send the welcome email.');
        }

        $subject    = $this->translator->trans('booking.first.subject', [], 'email');
        $salutation = $this->translator->trans('booking.first.salutation', ['%firstName%' => $user->getFirstName()], 'email');

        $context = [
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $salutation,
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans('booking.first.instructions', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION  => $this->translator->trans('booking.first.explanation', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE    => $this->translator->trans('booking.first.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT      => $subject,
            ],
        ];

        $this->userMailer->sendTemplatedEmail($userEmail, $subject, $context);
    }
}
