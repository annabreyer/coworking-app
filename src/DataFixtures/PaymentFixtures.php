<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppFixtures::class,
            BookingFixtures::class,
            PriceFixtures::class,
            InvoiceFixtures::class,
            VoucherFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->loadPaymentForUserOneVouchers($manager);
        $this->loadPaymentWithSingleUserVoucher($manager);
        $this->loadPaymentForPaidBooking($manager);
    }

    private function loadPaymentForUserOneVouchers(ObjectManager $manager): void
    {
        $user                = $this->getReference('user1');
        $voucherToBeExcluded = $this->getReference('voucher-without-payment', Voucher::class);

        foreach ($user->getVouchers() as $voucher) {
            if ($voucher === $voucherToBeExcluded) {
                continue;
            }
            $payment = new Payment($voucher->getInvoice(), Payment::PAYMENT_TYPE_TRANSACTION);
            $payment->setVoucher($voucher);
            $payment->setAmount($voucher->getValue());
            $payment->setDate($voucher->getInvoice()->getDate());

            $manager->persist($payment);
        }
        $manager->flush();
    }

    private function loadPaymentWithSingleUserVoucher(ObjectManager $manager): void
    {
        $voucher = $this->getReference('single-use-voucher', Voucher::class);
        $booking = $this->getReference('booking-for-payment-with-voucher');

        $payment = new Payment($booking->getInvoice(), Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setVoucher($voucher);
        $payment->setAmount($voucher->getValue());
        $payment->setDate($booking->getBusinessDay()->getDate());

        $manager->persist($payment);

        $voucher->setUseDate($booking->getBusinessDay()->getDate());

        $manager->flush();
    }

    private function loadPaymentForPaidBooking(ObjectManager $manager): void
    {
        $invoice = $this->getReference('invoice-paid-booking', Invoice::class);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_TRANSACTION);
        $payment->setAmount($invoice->getAmount());
        $payment->setDate($invoice->getDate());

        $manager->persist($payment);
        $manager->flush();
    }
}
