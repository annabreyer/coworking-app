<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
        $this->entityManager->remove($booking);
        $this->entityManager->flush();
    }

    public function canBookingBeCancelled(Booking $booking): bool
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
}
