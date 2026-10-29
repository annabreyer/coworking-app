<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BookingType;
use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UpcomingBookingFixtures extends Fixture implements DependentFixtureInterface
{
    public const ONE_BOOKING_DAY_INDEX  = 1;
    public const FULLY_BOOKED_DAY_INDEX = 3;

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
            BookingTypeFixtures::class,
            UpcomingBusinessDayFixtures::class,
            CoworkingRoomFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $bookingsPerDay = [
            self::ONE_BOOKING_DAY_INDEX  => ['user2'],
            self::FULLY_BOOKED_DAY_INDEX => ['user1', 'user2', 'admin', 'firstBookingUser'],
        ];
        $room        = $this->getReference(CoworkingRoomFixtures::ROOM_REFERENCE, Room::class);
        $bookingType = $this->getReference(BookingTypeFixtures::FULL_DAY_REFERENCE, BookingType::class);

        foreach ($bookingsPerDay as $dayIndex => $userReferences) {
            $businessDay = $this->getReference(
                UpcomingBusinessDayFixtures::OPEN_DAY_REFERENCE_PREFIX . $dayIndex,
                BusinessDay::class
            );

            foreach ($userReferences as $userReference) {
                $booking = new Booking();
                $booking->setBusinessDay($businessDay);
                $booking->setRoom($room);
                $booking->setType($bookingType);
                $booking->setUser($this->getReference($userReference, User::class));
                $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

                $manager->persist($booking);
            }
        }

        $manager->flush();
    }
}
