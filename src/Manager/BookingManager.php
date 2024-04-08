<?php declare(strict_types = 1);

namespace App\Manager;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BookingManager
{
    public function __construct(private EntityManagerInterface $entityManager)
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
}
