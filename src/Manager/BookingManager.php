<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BookingType;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use App\Service\BookingMailerService;
use App\Service\InvoiceMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VoucherManager $voucherManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly BookingMailerService $bookingMailerService,
        private readonly InvoiceMailerService $invoiceMailerService,
        private readonly RefundManager $refundManager,
        private readonly string $timeLimitCancelBooking,
    ) {
    }

    public function saveBooking(User $user, BusinessDay $businessDay, Room $room, BookingType $bookingType): Booking
    {
        $booking = new Booking();
        $booking
            ->setUser($user)
            ->setBusinessDay($businessDay)
            ->setRoom($room)
            ->setType($bookingType)
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

    public function handleFinalizedBooking(Booking $booking): void
    {
        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            throw new \LogicException('Booking must have an invoice to be finalized.');
        }

        $this->invoiceManager->generateInvoicePdf($bookingInvoice);

        $userBookings = $booking->getUser()?->getBookings();
        if (null !== $userBookings && 1 === $userBookings->count()) {
            $this->bookingMailerService->sendFirstBookingEmail($booking);
        }

        if ($bookingInvoice->getAmount() > 0) {
            $this->invoiceMailerService->sendInvoiceToDocumentVault($bookingInvoice);
        }

        $this->invoiceMailerService->sendBookingInvoiceToUser($bookingInvoice);
    }

    public function cancelBooking(Booking $booking): void
    {
        $booking->setIsCancelled(true);
        $this->entityManager->flush();

        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            return;
        }

        $this->bookingMailerService->sendBookingCancelledEmail($booking);
        $this->refundBooking($booking);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function canBookingBeCancelledByUser(\DateTimeInterface $bookingDate): bool
    {
        $now   = $this->now();
        $limit = $this->now()->modify('-' . $this->timeLimitCancelBooking . 'days');

        if ($now < $limit) {
            throw new \LogicException('Time limit cancel booking is wrongly configured.');
        }

        $interval = $limit->diff($bookingDate);

        if (1 === $interval->invert) {
            return false;
        }

        return true;
    }

    public function refundBooking(Booking $booking): void
    {
        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            throw new \LogicException('Booking must have an invoice to be refunded.');
        }

        if (0 === $bookingInvoice->getPayments()->count()) {
            $this->refundManager->refundInvoiceWithReversalInvoice($bookingInvoice);

            return;
        }

        $this->refundManager->refundInvoiceWithVoucher($bookingInvoice);
    }
}
