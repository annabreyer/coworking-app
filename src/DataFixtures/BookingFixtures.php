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
    public function getDependencies()
    {
        return [
            AppFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadBookingsToFillRoomOne($manager);
        $this->loadBookingWithoutInvoice($manager);
        $this->loadBookingForInvoice($manager);
        $this->loadBookingForVoucherPayment($manager);
        $this->loadPaidBooking($manager);
    }

    private function loadBookingsToFillRoomOne(ObjectManager $manager): void
    {
        $businessDay = $this->getReference('businessDay-2024-04-01', BusinessDay::class);
        $room        = $this->getReference('room1', Room::class);

        for ($i = 0; $i < $room->getCapacity(); ++$i) {
            $booking = new Booking();
            $booking->setBusinessDay($businessDay);
            $booking->setRoom($room);
            $booking->setUser($this->getReference('user1', User::class));

            $manager->persist($booking);
        }
    }

    private function loadBookingForInvoice(ObjectManager $manager): void
    {
        $businessDay = $this->getReference('businessDay-2024-04-01', BusinessDay::class);
        $room3       = $this->getReference('room3', Room::class);
        $booking     = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room3);
        $booking->setUser($this->getReference('user1', User::class));
        $booking->setAmount(1500);

        $manager->persist($booking);
        $manager->flush();

        $this->addReference('booking-for-payment-by-invoice', $booking);
    }

    private function loadBookingWithoutInvoice(ObjectManager $manager): void
    {
        $room        = $this->getReference('room1', Room::class);
        $businessDay = $this->getReference('businessDay-2024-04-02', BusinessDay::class);
        $booking     = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($this->getReference('user1', User::class));
        $booking->setAmount(1500);

        $manager->persist($booking);
        $manager->flush();

        $this->addReference('booking-without-invoice', $booking);
    }

    private function loadBookingForVoucherPayment(ObjectManager $manager): void
    {
        $businessDay = $this->getReference('businessDay-2024-04-11', BusinessDay::class);
        $room        = $this->getReference('room3', Room::class);
        $booking     = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($this->getReference('user1', User::class));
        $booking->setAmount(1500);

        $manager->persist($booking);
        $manager->flush();

        $this->addReference('booking-for-payment-with-voucher', $booking);
    }

    private function loadPaidBooking(ObjectManager $manager): void
    {
        $businessDay = $this->getReference('businessDay-2024-04-12', BusinessDay::class);
        $room        = $this->getReference('room3', Room::class);
        $booking     = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($this->getReference('user1', User::class));
        $booking->setAmount(1500);

        $manager->persist($booking);
        $manager->flush();

        $this->addReference('paid-booking', $booking);
    }
}
