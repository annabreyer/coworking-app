<?php declare(strict_types = 1);

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
    )
    {
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

    public function cancelBooking(Booking $booking): void
    {
        $this->entityManager->remove($booking);
        $this->entityManager->flush();
    }

    public function canBookingBeCancelled(Booking $booking): bool
    {
        $now   = $this->now();
        $limit = $this->now()->modify('-' . $this->timeLimitCancelBooking);

        if ($now < $limit){
            throw new \LogicException('Time limit cancel booking is wrongly configured');
        }

        $interval = $limit->diff($booking->getBusinessDay()->getDate());

        if ($interval->days >= $this->timeLimitCancelBooking){
            return true;
        }

        return false;
    }
}
