<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Price;
use App\Entity\User;
use App\Manager\InvoiceManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class InvoiceBulkDownloadFixtures extends Fixture implements DependentFixtureInterface
{
    use ClockAwareTrait;

    public const MONTHS             = 3;
    public const INVOICES_PER_MONTH = 4;
    public const UNPAID_EVERY_NTH   = 3;

    private const CUSTOMER_REFERENCES = ['user1', 'user2', 'firstBookingUser'];
    private const PRICE_REFERENCES    = ['price-single', 'price-voucher'];

    public function __construct(
        private readonly InvoiceManager $invoiceManager,
        private readonly string $invoicePrefix,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
            PriceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $customers    = $this->getCustomers();
        $prices       = $this->getPrices();
        $firstOfMonth = $this->now()
                             ->modify('first day of this month')
                             ->setTime(0, 0)
        ;

        $nextNumber = (int) substr($this->invoiceManager->getInvoiceNumber(), \strlen($this->invoicePrefix));

        $count = 0;
        for ($month = self::MONTHS; $month >= 1; --$month) {
            $monthStart = $firstOfMonth->modify(\sprintf('-%d months', $month));

            for ($i = 0; $i < self::INVOICES_PER_MONTH; ++$i) {
                $price       = $prices[$count % \count($prices)];
                $date        = \DateTime::createFromImmutable($monthStart->modify(\sprintf('+%d days', 2 + $i * 6)));

                $invoice = new Invoice();
                $invoice->setUser($customers[$count % \count($customers)])
                        ->setAmount($price->getAmount())
                        ->setDescription($price->getName())
                        ->setDate($date)
                        ->setNumber($this->invoicePrefix . $nextNumber++)
                ;

                $isUnpaid = 0 === ($count + 1) % self::UNPAID_EVERY_NTH;
                if (false === $isUnpaid) {
                    $payment = new Payment();
                    $payment->setType(Payment::PAYMENT_TYPE_TRANSACTION)
                            ->setAmount($price->getAmount())
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
    private function getCustomers(): array
    {
        return array_map(
            fn (string $reference): User => $this->getReference($reference, User::class),
            self::CUSTOMER_REFERENCES
        );
    }

    /**
     * @return Price[]
     */
    private function getPrices(): array
    {
        return array_map(
            fn (string $reference): Price => $this->getReference($reference, Price::class),
            self::PRICE_REFERENCES
        );
    }
}
