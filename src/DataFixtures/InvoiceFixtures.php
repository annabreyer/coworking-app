<?php declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadInvoices($manager);
    }

    private function loadInvoices(ObjectManager $manager)
    {
        $booking = $this->getReference('booking1', Booking::class);
        $user = $this->getReference('user1', User::class);

        $invoice = new Invoice();
        $invoice->addBooking($booking)
                ->setUser($user)
                ->setAmount(1500)
                ->setDate(new \DateTime('2024-03-28'))
                ->setNumber('2024CO0001');

        $manager->persist($invoice);
        $manager->flush();
    }
}
