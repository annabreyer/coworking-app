<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Invoice;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public const BOOKING_INVOICE_NUMBER = 'CO20240001';
    public const VOUCHER_INVOICE_NUMBER = 'CO20240002';

    public function getDependencies()
    {
        return [
            AppFixtures::class,
            BookingFixtures::class,
            PriceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadBookingInvoice($manager);
        $this->loadVoucherInvoice($manager);
        $this->loadInvoiceFromLastYear($manager);
    }

    private function loadBookingInvoice(ObjectManager $manager)
    {
        $booking = $this->getReference('booking-2024-04-01-room3', Booking::class);

        $invoice = new Invoice();
        $invoice->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount(1500)
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber(self::BOOKING_INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();
    }

    private function loadVoucherInvoice(ObjectManager $manager): void
    {
        $voucherPrice = $this->getReference('price-voucher');
        $user         = $this->getReference('user1');

        $invoice = new Invoice();
        $invoice->setAmount($voucherPrice->getAmount())
            ->setUser($user)
            ->setVouchers($user->getVouchers())
            ->setDate(new \DateTime('2024-04-04'))
            ->setNumber(self::VOUCHER_INVOICE_NUMBER)
            ->setAmount($voucherPrice->getAmount());

        $manager->persist($invoice);
        $manager->flush();
    }

    private function loadInvoiceFromLastYear(ObjectManager $manager): void
    {
        $user = $this->getReference('user1');
        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount(1500)
                ->setDate(new \DateTime('2023-03-28'))
                ->setNumber('CO20230001')
        ;

        $manager->persist($invoice);
        $manager->flush();
    }
}
