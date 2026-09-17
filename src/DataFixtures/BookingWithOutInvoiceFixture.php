<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use Doctrine\Persistence\ObjectManager;

class BookingWithOutInvoiceFixture extends BookingFixtures
{
    public const BUSINESS_DAY_DATE = self::BOOKING_WITHOUT_INVOICE_DATE;

    public function load(ObjectManager $manager): void
    {
        parent::load($manager);

        $user        = $this->getReference('user1', \App\Entity\User::class);
        $room        = $this->getReference('room3', \App\Entity\Room::class);
        $businessDay = $this->getReference('businessDay-' . self::BUSINESS_DAY_DATE, \App\Entity\BusinessDay::class);

        $booking = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

        $manager->persist($booking);
        $manager->flush();
    }
}
