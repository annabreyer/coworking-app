<?php

declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Price;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Manager\VoucherManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VoucherFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppFixtures::class,
            PriceFixtures::class,
            InvoiceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadTenVouchersPaidByInvoice($manager);
        $this->loadSingleVoucherForBookingPayment($manager);
        $this->loadVoucherForAdmin($manager);
        $this->loadExpiredVoucher($manager);
        $this->setOneVoucherUsed($manager);
        $this->loadVoucherWithoutPayment($manager);
    }

    private function loadTenVouchersPaidByInvoice(ObjectManager $manager): void
    {
        $user        = $this->getReference('user1');
        $voucherType = $this->getReference('voucherType10Units');
        $singlePrice = $this->getReference('price-single', Price::class);
        $invoice     = $this->getReference('invoice-voucher', Invoice::class);

        $vouchers        = $voucherType->getUnits();
        $createdVouchers = new ArrayCollection();
        for ($i = 0; $i < $vouchers; ++$i) {
            $now        = new \DateTimeImmutable('2024-04-04');
            $expiryDate = $now->modify('+' . $voucherType->getValidityMonths() . ' months');

            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode(VoucherManager::generateVoucherCode());
            $voucher->setExpiryDate($expiryDate);
            $voucher->setValue($singlePrice->getAmount());
            $voucher->setInvoice($invoice);
            $manager->persist($voucher);
            $createdVouchers[] = $voucher;
        }
        $manager->flush();
    }

    private function loadSingleVoucherForBookingPayment(ObjectManager $manager): void
    {
        $booking = $this->getReference('booking-for-payment-with-voucher');

        $voucherType = new VoucherType();
        $voucherType->setValidityMonths(1)
                    ->setUnits(1)
        ;

        $manager->persist($voucherType);
        $this->addReference('single-use-voucher-type', $voucherType);

        $voucher = new Voucher();
        $voucher->setUser($booking->getUser())
                ->setCode('VO20240001')
                ->setExpiryDate(new \DateTime('2024-05-11'))
                ->setVoucherType($voucherType)
                ->setValue(1500)
        ;

        $manager->persist($voucher);
        $voucher->setInvoice($booking->getInvoice());
        $manager->flush();

        $this->addReference('single-use-voucher', $voucher);
    }

    private function loadVoucherForAdmin(ObjectManager $manager): void
    {
        $voucherType = $this->getReference('single-use-voucher-type', VoucherType::class);
        $user        = $this->getReference('admin');

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode('VO20240002')
                ->setExpiryDate(new \DateTime('2024-05-11'))
                ->setVoucherType($voucherType)
                ->setValue(1500)
        ;

        $manager->persist($voucher);
        $manager->flush();
    }

    private function loadExpiredVoucher(ObjectManager $manager): void
    {
        $voucherType = $this->getReference('single-use-voucher-type', VoucherType::class);
        $user        = $this->getReference('user1');
        $invoice     = $this->getReference('invoice-expired-voucher', Invoice::class);

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode('VO20240004')
                ->setExpiryDate(new \DateTime('2024-01-11'))
                ->setVoucherType($voucherType)
                ->setValue(1500)
                ->setInvoice($invoice)
        ;

        $manager->persist($voucher);
        $manager->flush();
    }

    private function setOneVoucherUsed(ObjectManager $manager): void
    {
        $user        = $this->getReference('user1');
        $voucherType = $this->getReference('voucherType10Units');

        foreach ($user->getVouchers() as $voucher) {
            if ($voucher->getVoucherType() === $voucherType) {
                $voucher->setUseDate(new \DateTime('2024-04-06'));

                $manager->flush();
                break;
            }
        }
    }

    private function loadVoucherWithoutPayment(ObjectManager $manager): void
    {
        $voucherType = $this->getReference('single-use-voucher-type', VoucherType::class);
        $user        = $this->getReference('user1');
        $invoice     = $this->getReference('invoice-voucher-without-payment', Invoice::class);

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode('VO20240033')
                ->setExpiryDate(new \DateTime('2025-01-11'))
                ->setVoucherType($voucherType)
                ->setValue(1500)
                ->setInvoice($invoice)
        ;

        $manager->persist($voucher);
        $manager->flush();

        $this->addReference('voucher-without-payment', $voucher);
    }
}
