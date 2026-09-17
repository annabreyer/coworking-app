<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Loads a user, a booking, and an invoice (no payment) for targeted tests.
 */
class BookingWithInvoiceNoPaymentFixture extends Fixture implements DependentFixtureInterface
{
    public const INVOICE_NUMBER = 'INV-NO-PAY-001';
    public const BOOKING_DATE = '2024-04-02';

    public function getDependencies(): array
    {
        return [UserAndAdminFixture::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $user */
        $user = $this->getReference('user.one', User::class);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setAmount(100);
        $booking->setBusinessDay(new \DateTimeImmutable(self::BOOKING_DATE)); // Adjust as needed
        $manager->persist($booking);

        $invoice = new Invoice();
        $invoice->setUser($user)
                ->addBooking($booking)
                ->setAmount($booking->getAmount())
                ->setDate(new \DateTimeImmutable(self::BOOKING_DATE))
                ->setNumber(self::INVOICE_NUMBER);
        $manager->persist($invoice);

        $manager->flush();
    }
} 