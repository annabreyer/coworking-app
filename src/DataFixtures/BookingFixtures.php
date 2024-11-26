<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookingFixtures extends Fixture implements DependentFixtureInterface
{
    public const BOOKING_WITH_INVOICE_NO_PAYMENT_DATE             = '2024-04-02';
    public const BOOKING_WITH_INVOICE_WITH_PAYMENT_DATE           = '2024-03-01';
    public const BOOKING_WITHOUT_AMOUNT_DATE                      = '2024-03-02';
    public const BOOKING_WITHOUT_INVOICE_DATE                     = '2024-03-06';
    public const BOOKING_TO_BE_CANCELLED_DATE                     = '2024-03-14';
    public const BOOKING_STANDARD_DATE                            = '2024-04-01';
    public const BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER   = 'CO202400328';
    public const BOOKING_WITH_INVOICE_WITH_PAYMENT_INVOICE_NUMBER = 'CO202400325';
    public const FIRST_BOOKING_DATE = '2024-04-30';
    public const FIRST_BOOKING_INVOICE_NUMBER = 'CO202400400';

    public function getDependencies()
    {
        return [
            BasicFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $user = $this->getReference('user1', User::class);

        $this->loadBookingsToFillRoomOne($manager, $user);
        $this->loadBookingToBeCancelled($manager, $user);
        $this->loadStandardBooking($manager, $user);
    }

    private function loadBookingsToFillRoomOne(ObjectManager $manager, User $user): void
    {
        $room        = $this->getReference('room1', Room::class);
        $businessDay = $this->getReference('businessDay-' . self::BOOKING_STANDARD_DATE, BusinessDay::class);

        for ($i = 0; $i < $room->getCapacity(); ++$i) {
            $booking = new Booking();
            $booking->setBusinessDay($businessDay);
            $booking->setRoom($room);
            $booking->setUser($user);
            $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

            $manager->persist($booking);
        }

        $manager->flush();
    }

    private function loadStandardBooking(ObjectManager $manager, User $user): void
    {
        $room = $this->getReference('room3', Room::class);

        $booking = new Booking();
        $booking->setBusinessDay($this->getReference('businessDay-' . self::BOOKING_STANDARD_DATE, BusinessDay::class));
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

        $manager->persist($booking);
        $manager->flush();
    }

    private function loadBookingToBeCancelled(ObjectManager $manager, User $user): void
    {
        $room = $this->getReference('room3', Room::class);

        $booking = new Booking();
        $booking->setBusinessDay($this->getReference('businessDay-' . self::BOOKING_TO_BE_CANCELLED_DATE, BusinessDay::class));
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

        $manager->persist($booking);
        $manager->flush();
    }
}
