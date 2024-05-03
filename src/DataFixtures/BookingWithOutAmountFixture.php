<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use Doctrine\Persistence\ObjectManager;

class BookingWithOutAmountFixture extends BookingFixtures
{
    public const BUSINESS_DAY_DATE = self::BOOKING_WITHOUT_AMOUNT_DATE;

    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $businessDay = $this->getReference('businessDay-' . self::BUSINESS_DAY_DATE);
        $user        = $this->getReference('user1');
        $room        = $this->getReference('room3');

        $booking = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($user);

        $manager->persist($booking);
        $manager->flush();
    }
}
