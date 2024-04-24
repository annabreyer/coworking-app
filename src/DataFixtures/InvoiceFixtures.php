<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\User;
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
        $this->loadBookingInvoices($manager);
        $this->loadVoucherInvoice($manager);
        $this->loadInvoiceFromLastYear($manager);
        $this->loadInvoiceForSingleUseVoucher($manager);
        $this->loadInvoiceForExpiredVoucher($manager);
        $this->loadInvoiceForVoucherWhichWillNotHaveAPayment($manager);
        $this->loadInvoiceForPaidBooking($manager);
    }

    private function loadBookingInvoices(ObjectManager $manager)
    {
        $booking1 = $this->getReference('booking-for-payment-by-invoice', Booking::class);
        $invoice1 = $this->getBookingInvoice($booking1, $manager);
        $this->addReference('invoice-for-payment-by-invoice', $invoice1);

        $booking2 = $this->getReference('booking-for-payment-with-voucher', Booking::class);
        $invoice2 = $this->getBookingInvoice($booking2, $manager);
        $this->addReference('invoice-for-single-use-voucher', $invoice2);
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
                ->setAmount($voucherPrice->getAmount())
        ;

        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-voucher', $invoice);
    }

    private function loadInvoiceFromLastYear(ObjectManager $manager): void
    {
        $user    = $this->getReference('user1');
        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount(1500)
                ->setDate(new \DateTime('2023-03-28'))
                ->setNumber('CO20230001')
        ;

        $manager->persist($invoice);
        $manager->flush();
    }

    private function loadInvoiceForSingleUseVoucher(ObjectManager $manager): void
    {
        $user  = $this->getReference('user1', User::class);
        $price = $this->getReference('price-single', Price::class);

        $invoice = new Invoice();
        $invoice->setAmount($price->getAmount())
                ->setUser($user)
                ->setDate(new \DateTime('2024-04-04'))
                ->setNumber('CO20240010')
        ;
        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-single-use-voucher', $invoice);
    }

    private function getBookingInvoice(Booking $booking, ObjectManager $manager): Invoice
    {
        $invoice = new Invoice();
        $invoice->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($booking->getAmount())
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber(self::BOOKING_INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();

        return $invoice;
    }

    private function loadInvoiceForExpiredVoucher(ObjectManager $manager): void
    {
        $user  = $this->getReference('user1', User::class);
        $price = $this->getReference('price-single', Price::class);

        $invoice = new Invoice();
        $invoice->setAmount($price->getAmount())
                ->setUser($user)
                ->setDate(new \DateTime('2024-04-04'))
                ->setNumber('CO20240011')
        ;
        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-expired-voucher', $invoice);
    }

    private function loadInvoiceForVoucherWhichWillNotHaveAPayment(ObjectManager $manager): void
    {
        $user  = $this->getReference('user1', User::class);
        $price = $this->getReference('price-single', Price::class);

        $invoice = new Invoice();
        $invoice->setAmount($price->getAmount())
                ->setUser($user)
                ->setDate(new \DateTime('2024-04-04'))
                ->setNumber('CO20240033')
        ;
        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-voucher-without-payment', $invoice);
    }

    private function loadInvoiceForPaidBooking(ObjectManager $manager): void
    {
        $booking = $this->getReference('paid-booking', Booking::class);

        $invoice = new Invoice();
        $invoice->addBooking($booking)
                ->setUser($booking->getUser())
                ->setAmount($booking->getAmount())
                ->setDate($booking->getBusinessDay()->getDate())
                ->setNumber('CO20240044')
        ;

        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-paid-booking', $invoice);
    }
}
