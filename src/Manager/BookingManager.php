<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Price;
use App\Entity\Room;
use App\Entity\User;
use App\Entity\Voucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly PaymentManager $paymentManager,
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

    public function addAmountToBooking(Booking $booking, Price $price): void
    {
        $booking->setAmount($price->getAmount());
        $this->entityManager->flush();
    }

    public function cancelBooking(Booking $booking): void
    {
        $this->entityManager->remove($booking);
        $this->entityManager->flush();
    }

    public function canBookingBeCancelled(Booking $booking): bool
    {
        if (null === $booking->getBusinessDay() || null === $booking->getBusinessDay()->getDate()) {
            throw new \LogicException('Booking must have a business day and a date.');
        }

        $now   = $this->now();
        $limit = $this->now()->modify('-' . $this->timeLimitCancelBooking);

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

    public function handleBookingPaymentByInvoice(Booking $booking): void
    {
        $invoice = $this->invoiceManager->createInvoiceFromBooking($booking, $booking->getAmount());

        $this->invoiceManager->generateBookingInvoicePdf($invoice);
        $this->invoiceManager->sendBookingInvoiceToUser($invoice);
        $this->invoiceManager->sendInvoiceToDocumentVault($invoice);
    }

    public function handleBookingPaymentByVoucher(Booking $booking, Voucher $voucher): void
    {
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $invoice = $this->invoiceManager->createInvoiceFromBooking($booking, $booking->getAmount());
            $this->paymentManager->handleVoucherPayment($invoice, $voucher);

            $this->entityManager->getConnection()->commit();
        } catch (\Exception $exception) {
            $this->entityManager->getConnection()->rollBack();
            throw $exception;
        }

        $this->invoiceManager->generateBookingInvoicePdf($invoice);
        $this->invoiceManager->sendBookingInvoiceToUser($invoice);
    }

    public function handleBookingPaidByPaypal(Booking $booking): void
    {
        $this->invoiceManager->generateBookingInvoicePdf($booking->getInvoice());
        $this->invoiceManager->sendBookingInvoiceToUser($booking->getInvoice());
        $this->invoiceManager->sendInvoiceToDocumentVault($booking->getInvoice());
    }
}
