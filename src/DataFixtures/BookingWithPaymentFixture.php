<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use Doctrine\Persistence\ObjectManager;

class BookingWithPaymentFixture extends BookingFixtures
{
    public const INVOICE_NUMBER    = self::BOOKING_WITH_INVOICE_WITH_PAYMENT_INVOICE_NUMBER;
    public const BUSINESS_DAY_DATE = self::BOOKING_WITH_INVOICE_WITH_PAYMENT_DATE;

    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $user        = $this->getReference('user1');
        $room        = $this->getReference('room3');
        $businessDay = $this->getReference('businessDay-' . self::BUSINESS_DAY_DATE);

        $booking = new Booking();
        $booking->setBusinessDay($businessDay);
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

        $manager->persist($booking);
        $manager->flush();

        $invoice = new Invoice();
        $invoice->setAmount($booking->getAmount());
        $invoice->setUser($user);
        $invoice->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($booking->getAmount())
                ->setDate(new \DateTime('2024-03-25'))
                ->setNumber(self::INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setInvoice($invoice)
                ->setAmount($invoice->getAmount())
                ->setDate($invoice->getDate())
        ;

        $manager->persist($payment);
        $manager->flush();
    }
}
