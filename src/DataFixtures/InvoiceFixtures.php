<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public const STANDARD_BOOKING_INVOICE_NUMBER = 'CO20240001';
    public const VOUCHER_INVOICE_NUMBER          = 'CO20242000';

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadInvoiceFromLastYear($manager);
        $this->loadStandardBookingInvoice($manager);
        $this->loadVoucherInvoiceWithoutVouchers($manager);
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

    private function loadVoucherInvoiceWithoutVouchers(ObjectManager $manager): void
    {
        $user    = $this->getReference('user1');
        $invoice = new Invoice();
        $invoice->setUser($user)
                ->setAmount(13500)
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber(self::VOUCHER_INVOICE_NUMBER)
        ;

        $manager->persist($invoice);
        $manager->flush();
    }
}
