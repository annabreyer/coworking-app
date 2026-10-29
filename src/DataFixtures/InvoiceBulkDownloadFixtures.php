<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Manager\InvoiceManager;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class InvoiceBulkDownloadFixtures extends Fixture
{
    use ClockAwareTrait;

    public const MONTHS             = 3;
    public const INVOICES_PER_MONTH = 4;
    public const UNPAID_EVERY_NTH   = 3;

    private const CUSTOMERS = [
        ['invoice.customer1@annabreyer.dev', 'Erika', 'Mustermann'],
        ['invoice.customer2@annabreyer.dev', 'Max', 'Mustermann'],
        ['invoice.customer3@annabreyer.dev', 'Lieschen', 'Müller'],
    ];

    private const DESCRIPTIONS = [
        'Coworking Tagespass'      => 1500,
        'Coworking 10er-Karte'     => 13500,
        'Besprechungsraum 2 Std.'  => 3000,
        'Coworking Monatsabo'      => 19900,
    ];

    public function __construct(
        private readonly InvoiceManager $invoiceManager,
        private readonly UserRepository $userRepository,
        private readonly string $invoicePrefix,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $customers    = $this->loadCustomers($manager);
        $descriptions = array_keys(self::DESCRIPTIONS);
        $firstOfMonth = $this->now()->modify('first day of this month')->setTime(0, 0);

        $nextNumber = (int) substr($this->invoiceManager->getInvoiceNumber(), \strlen($this->invoicePrefix));

        $count = 0;
        for ($month = self::MONTHS; $month >= 1; --$month) {
            $monthStart = $firstOfMonth->modify(\sprintf('-%d months', $month));

            for ($i = 0; $i < self::INVOICES_PER_MONTH; ++$i) {
                $description = $descriptions[$count % \count($descriptions)];
                $date        = \DateTime::createFromImmutable($monthStart->modify(\sprintf('+%d days', 2 + $i * 6)));

                $invoice = new Invoice();
                $invoice->setUser($customers[$count % \count($customers)])
                        ->setAmount(self::DESCRIPTIONS[$description])
                        ->setDescription($description)
                        ->setDate($date)
                        ->setNumber($this->invoicePrefix . $nextNumber++)
                ;

                $isUnpaid = 0 === ($count + 1) % self::UNPAID_EVERY_NTH;
                if (false === $isUnpaid) {
                    $payment = new Payment();
                    $payment->setType(Payment::PAYMENT_TYPE_TRANSACTION)
                            ->setAmount(self::DESCRIPTIONS[$description])
                            ->setDate($date)
                    ;
                    $invoice->addPayment($payment);
                }

                $manager->persist($invoice);
                ++$count;
            }
        }

        $manager->flush();
    }

    /**
     * @return User[]
     */
    private function loadCustomers(ObjectManager $manager): array
    {
        $customers = [];
        foreach (self::CUSTOMERS as [$email, $firstName, $lastName]) {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (null === $user) {
                $user = new User();
                $user->setEmail($email)
                     ->setFirstName($firstName)
                     ->setLastName($lastName)
                     ->setMobilePhone('0151 23456789')
                ;
                $manager->persist($user);
            }

            $customers[] = $user;
        }
        $manager->flush();

        return $customers;
    }
}
