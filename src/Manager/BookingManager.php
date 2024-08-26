<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use App\Service\BookingMailerService;
use App\Service\InvoiceMailerService;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingManager
{
    use ClockAwareTrait;
    use EmailContextTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VoucherManager $voucherManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly BookingMailerService $bookingMailerService,
        private readonly InvoiceMailerService $invoiceMailerService,
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

    public function handleFinalizedBooking(Booking $booking): void
    {
        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            throw new \LogicException('Booking must have an invoice to be finalized.');
        }

        $this->invoiceManager->generateBookingInvoicePdf($bookingInvoice);

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

        if ($booking->isFullyPaid()) {
            $this->refundBooking($booking);
        } else {
            $this->invoiceManager->cancelUnpaidInvoice($bookingInvoice);
        }

        $this->bookingMailerService->sendBookingCancelledEmail($booking);
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

        $user = $booking->getUser();
        if (null === $user) {
            throw new \LogicException('Booking must have a user to be refunded.');
        }

        $expiryDate = null;

        if ($bookingInvoice->isFullyPaidByVoucher()) {
            $payment = $bookingInvoice->getPayments()->first();

            if (false === $payment) {
                throw new \LogicException('Fully paid invoice must have a payment.');
            }

            $paymentVoucher = $payment->getVoucher();
            if (null === $paymentVoucher) {
                throw new \LogicException('Invoice fully paid by voucher must have a voucher.');
            }

            $expiryDate = $paymentVoucher->getExpiryDate();
        }

        $voucher = $this->voucherManager->createRefundVoucher($user, $booking->getAmount(), $expiryDate);
        $voucher->setInvoice($bookingInvoice);

        $this->entityManager->flush();
    }
}
