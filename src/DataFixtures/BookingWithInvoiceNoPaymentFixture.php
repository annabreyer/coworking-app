<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Invoice;
use Doctrine\Persistence\ObjectManager;

class BookingWithInvoiceNoPaymentFixture extends BookingFixtures
{
    public const INVOICE_NUMBER    = self::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER;
    public const BUSINESS_DAY_DATE = self::BOOKING_WITH_INVOICE_NO_PAYMENT_DATE;

    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->loadBookingWithInvoiceNoPayment($manager);
        $this->loadFirstBookingFixture($manager);
    }

    private function loadBookingWithInvoiceNoPayment(ObjectManager $manager): void
    {
        $user        = $this->getReference('user1');
        $room        = $this->getReference('room3');
        $businessDay = $this->getReference('businessDay-' . self::BUSINESS_DAY_DATE);

        $booking = new Booking();
        $booking->setBusinessDay($businessDay)
                ->setRoom($room)
                ->setUser($user)
                ->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
        ;

        $manager->persist($booking);
        $manager->flush();

        $invoice = new Invoice();
        $invoice->setAmount($booking->getAmount())
                ->setUser($user)
                ->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($booking->getAmount())
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber(self::INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();
    }

    private function loadFirstBookingFixture(ObjectManager $manager): void
    {
        $user        = $this->getReference('firstBookingUser');
        $room        = $this->getReference('room3');
        $businessDay = $this->getReference('businessDay-' . self::FIRST_BOOKING_DATE);

        $booking = new Booking();
        $booking->setBusinessDay($businessDay)
                ->setRoom($room)
                ->setUser($user)
                ->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
        ;

        $manager->persist($booking);
        $manager->flush();

        $invoice = new Invoice();
        $invoice->setAmount($booking->getAmount())
                ->setUser($user)
                ->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($booking->getAmount())
                ->setDate(new \DateTime('2024-06-28'))
                ->setNumber(self::FIRST_BOOKING_INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();
    }
}
