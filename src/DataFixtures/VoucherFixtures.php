<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Price;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Manager\VoucherManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VoucherFixtures extends Fixture implements DependentFixtureInterface
{
    public const ADMIN_VOUCHER_CODE           = 'VO20240002';
    public const EXPIRED_VOUCHER_CODE         = 'VO20240004';
    public const VOUCHER_WITHOUT_PAYMENT_CODE = 'VO20240033';
    public const VOUCHER_INVOICE_NUMBER       = 'CO20240002';
    public const ALREADY_USED_VOUCHER_CODE    = 'VO20240044';

    public function getDependencies()
    {
        return [
            BasicFixtures::class,
            PriceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $user        = $this->getReference('user1', User::class);
        $voucherType = $this->getReference('single-use-voucher-type', VoucherType::class);

        $this->loadTenVouchersPaidByInvoice($manager, $user);
        $this->loadVoucherForAdmin($manager, $voucherType);
        $this->loadExpiredVoucher($manager, $user, $voucherType);
        $this->loadVoucherWithoutPayment($manager, $user, $voucherType);
        $this->loadAlreadyUsedVoucher($manager, $user, $voucherType);
    }

    private function loadTenVouchersPaidByInvoice(ObjectManager $manager, User $user): void
    {
        $voucherPrice = $this->getReference('price-voucher', Price::class);
        $invoice      = new Invoice();
        $invoice->setAmount($voucherPrice->getAmount())
                ->setUser($user)
                ->setVouchers($user->getVouchers())
                ->setDate(new \DateTimeImmutable('2024-04-04'))
                ->setNumber(self::VOUCHER_INVOICE_NUMBER)
                ->setAmount($voucherPrice->getAmount())
        ;
        $manager->persist($invoice);

        $payment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setInvoice($invoice);
        $payment->setAmount($voucherPrice->getAmount());
        $payment->setDate($invoice->getDate());

        $manager->persist($payment);

        $singlePrice = $this->getReference('price-single', Price::class);
        $vouchers    = $voucherPrice->getVoucherType()->getUnits();
        for ($i = 0; $i < $vouchers; ++$i) {
            $now        = new \DateTimeImmutable('2024-04-04');
            $expiryDate = $now->modify('+' . $voucherPrice->getVoucherType()->getValidityMonths() . ' months');

            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherPrice->getVoucherType());
            $voucher->setCode(VoucherManager::generateVoucherCode());
            $voucher->setExpiryDate($expiryDate);
            $voucher->setValue($singlePrice->getAmount());
            $voucher->setInvoice($invoice);
            $manager->persist($voucher);
        }
        $manager->flush();
    }

    private function loadVoucherForAdmin(ObjectManager $manager, VoucherType $voucherType): void
    {
        $user    = $this->getReference('admin');
        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode(self::ADMIN_VOUCHER_CODE)
                ->setExpiryDate(new \DateTime('2024-05-11'))
                ->setVoucherType($voucherType)
                ->setValue(PriceFixtures::SINGLE_PRICE_AMOUNT)
        ;

        $manager->persist($voucher);
        $manager->flush();
    }

    private function loadExpiredVoucher(ObjectManager $manager, User $user, VoucherType $voucherType): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setUser($user)
                ->setDate(new \DateTime('2024-04-04'))
                ->setNumber('CO20240011')
        ;
        $manager->persist($invoice);
        $manager->flush();

        $this->addReference('invoice-expired-voucher', $invoice);

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode(self::EXPIRED_VOUCHER_CODE)
                ->setExpiryDate(new \DateTime('2024-01-11'))
                ->setVoucherType($voucherType)
                ->setValue(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setInvoice($invoice)
        ;

        $manager->persist($voucher);
        $manager->flush();
    }

    private function loadVoucherWithoutPayment(ObjectManager $manager, User $user, VoucherType $voucherType): void
    {
        $invoice = new Invoice();
        $invoice->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setUser($user)
                ->setDate(new \DateTime('2024-04-04'))
                ->setNumber('CO20240033')
        ;
        $manager->persist($invoice);
        $manager->flush();

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode(self::VOUCHER_WITHOUT_PAYMENT_CODE)
                ->setExpiryDate(new \DateTime('2025-01-11'))
                ->setVoucherType($voucherType)
                ->setValue(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setInvoice($invoice)
        ;

        $manager->persist($voucher);
        $manager->flush();
    }

    private function loadAlreadyUsedVoucher(ObjectManager $manager, User $user, VoucherType $voucherType): void
    {
        $voucherPurchaseDate = new \DateTimeImmutable('2024-03-04');
        $voucherPaymentDate  = new \DateTimeImmutable('2024-03-05');
        $bookingDate         = new \DateTimeImmutable('2024-03-21');
        $businessDay         = $this->getReference('businessDay-' . $bookingDate->format('Y-m-d'), BusinessDay::class);
        $bookingInvoiceDate  = new \DateTimeImmutable('2024-03-14');

        $voucherInvoice = new Invoice();
        $voucherInvoice->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                       ->setUser($user)
                       ->setDate($voucherPurchaseDate)
                       ->setNumber('CO202400304')
        ;
        $manager->persist($voucherInvoice);
        $manager->flush();

        $voucherInvoicePayment = new Payment(Payment::PAYMENT_TYPE_TRANSACTION);
        $voucherInvoicePayment->setInvoice($voucherInvoice);
        $voucherInvoicePayment->setAmount(0);
        $voucherInvoicePayment->setDate($voucherPaymentDate);
        $manager->persist($voucherInvoicePayment);
        $manager->flush();

        $voucher = new Voucher();
        $voucher->setUser($user)
                ->setCode(self::ALREADY_USED_VOUCHER_CODE)
                ->setExpiryDate(new \DateTime('2025-01-11'))
                ->setVoucherType($voucherType)
                ->setValue(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setInvoice($voucherInvoice)
                ->setUseDate($bookingInvoiceDate)
        ;

        $manager->persist($voucher);
        $manager->flush();

        $booking = new Booking();
        $booking->setUser($user)
                ->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT)
                ->setBusinessDay($businessDay)
                ->setRoom($this->getReference('room3'))
                ->setUser($user)
        ;

        $bookingInvoice = new Invoice();
        $bookingInvoice->setAmount(0)
                       ->setUser($user)
                       ->setDate($bookingInvoiceDate)
                       ->setNumber('CO202400321')
        ;
        $manager->persist($bookingInvoice);
        $manager->flush();

        $bookingVoucherPayment = new Payment(Payment::PAYMENT_TYPE_VOUCHER);
        $bookingVoucherPayment->setInvoice($bookingInvoice);
        $bookingVoucherPayment->setAmount($voucher->getValue());
        $bookingVoucherPayment->setVoucher($voucher);
        $bookingVoucherPayment->setDate($bookingInvoiceDate);

        $manager->persist($bookingVoucherPayment);
        $manager->flush();
    }
}
