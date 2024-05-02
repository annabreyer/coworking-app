<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public const STANDARD_BOOKING_INVOICE_NUMBER = 'CO20240001';
    public function getDependencies()
    {
        return [
            BasicFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadInvoiceFromLastYear($manager);
        $this->loadStandardBookingInvoice($manager);
    }
    private function loadInvoiceFromLastYear(ObjectManager $manager): void
    {
        $user    = $this->getReference('user1');
        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setDate(new \DateTime('2023-03-28'))
                ->setNumber('CO20230001')
        ;

        $manager->persist($invoice);
        $manager->flush();
    }


    private function loadStandardBookingInvoice(ObjectManager $manager): void
    {
        $user    = $this->getReference('user1');
        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber(self::STANDARD_BOOKING_INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();
    }

}
