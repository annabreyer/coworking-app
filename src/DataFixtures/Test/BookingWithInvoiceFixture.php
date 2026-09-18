<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Room;
use App\Entity\BusinessDay;
use App\DataFixtures\PriceFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Loads a user, a booking (with business day and room), and an invoice for targeted tests.
 */
class BookingWithInvoiceFixture extends Fixture implements DependentFixtureInterface
{
    public const INVOICE_NUMBER = 'INV-BOOKING-001';
    public const BOOKING_DATE = '2024-04-03';
    public const ROOM_REF = 'room3';

    public function getDependencies(): array
    {
        return [\App\DataFixtures\BasicFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $user = $this->getReference('user1', User::class);
        $room = $this->getReference(self::ROOM_REF, Room::class);
        $businessDay = $this->getReference('businessDay-' . self::BOOKING_DATE, BusinessDay::class);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setRoom($room);
        $booking->setBusinessDay($businessDay);
        $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);
        $manager->persist($booking);

        $invoice = new Invoice();
        $invoice->setUser($user)
                ->addBooking($booking)
                ->setAmount($booking->getAmount())
                ->setDate($businessDay->getDate())
                ->setNumber(self::INVOICE_NUMBER);
        $manager->persist($invoice);

        $manager->flush();
    }
} 