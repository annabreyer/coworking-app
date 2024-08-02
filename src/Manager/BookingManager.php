<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingManager
{
    use ClockAwareTrait;
    use EmailContextTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VoucherManager $voucherManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $timeLimitCancelBooking
    ) {
    }

    public function saveBooking(User $user, BusinessDay $businessDay, Room $room): Booking
    {
        $booking = new Booking();
        $booking
            ->setUser($user)
            ->setBusinessDay($businessDay)
            ->setRoom($room)
        ;

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking;
    }

    public function addAmountToBooking(Booking $booking, int $priceAmount): void
    {
        $booking->setAmount($priceAmount);
        $this->entityManager->flush();
    }

    public function cancelBooking(Booking $booking): void
    {
        $booking->setIsCancelled(true);
        $this->entityManager->flush();

        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            return;
        }

        if ($booking->isFullyPaid()) {
            $this->refundBooking($booking);
        } else {
            $this->invoiceManager->cancelInvoice($bookingInvoice);
        }

        $this->sendBookingCancelledEmail($booking);
    }

    public function canBookingBeCancelledByUser(Booking $booking): bool
    {
        if (null === $booking->getBusinessDay()) {
            throw new \LogicException('Booking must have a business day and a date.');
        }

        $now   = $this->now();
        $limit = $this->now()->modify('-' . $this->timeLimitCancelBooking . 'days');

        if ($now < $limit) {
            throw new \LogicException('Time limit cancel booking is wrongly configured.');
        }

        $interval = $limit->diff($booking->getBusinessDay()->getDate());

        if (1 === $interval->invert) {
            return false;
        }

        if ($interval->days < $this->timeLimitCancelBooking) {
            return false;
        }

        return true;
    }

    public function refundBooking(Booking $booking): void
    {
        if (null === $booking->getAmount()) {
            throw new \LogicException('Booking must have an amount to be refunded.');
        }

        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            throw new \LogicException('Booking must have an invoice to be refunded.');
        }

        if (false === $bookingInvoice->isFullyPaid()) {
            throw new \LogicException('Booking invoice must be fully paid to be refunded.');
        }

        $expiryDate = null;

        if ($bookingInvoice->isFullyPaidByVoucher()) {
            $payment = $bookingInvoice->getPayments()->first();

            if (null === $payment) {
                throw new \LogicException('Fully paid invoice must have a payment.');
            }

            $paymentVoucher = $payment->getVoucher();
            if (null === $paymentVoucher) {
                throw new \LogicException('Invoice fully paid by voucher must have a voucher.');
            }

            $expiryDate = $paymentVoucher->getExpiryDate();
        }

        $voucher = $this->voucherManager->createRefundVoucher($booking->getUser(), $booking->getAmount(), $expiryDate);
        $voucher->setInvoice($bookingInvoice);

        $this->entityManager->flush();
    }

    public function sendBookingCancelledEmail(Booking $booking): void
    {
        $userEmail = $booking->getUser()?->getEmail();
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
        $salutation = $this->translator->trans('booking.cancel.salutation', ['%firstName%' => $booking->getUser()->getFirstName()], 'email');

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

        $email = (new TemplatedEmail())
            ->to(new Address($userEmail))
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
