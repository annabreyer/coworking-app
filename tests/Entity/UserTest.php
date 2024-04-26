<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetValidVouchersOnlyReturnsValidVouchers(): void
    {
        $user    = new User();
        $invoice = new Invoice();
        $invoice->setUser($user);
        $invoice->setAmount(100);

        $payment = new Payment($invoice, Payment::PAYMENT_TYPE_VOUCHER);
        $payment->setAmount(100);

        $voucher1 = new Voucher();
        $voucher1->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $voucher1->setInvoice($invoice);

        $user->addVoucher($voucher1);

        $voucher2 = new Voucher();
        $voucher2->setExpiryDate(new \DateTimeImmutable('yesterday'));
        $voucher2->setInvoice($invoice);
        $user->addVoucher($voucher2);

        $voucher3 = new Voucher();
        $voucher3->setExpiryDate(new \DateTimeImmutable('tomorrow'));
        $user->addVoucher($voucher3);

        $voucher4 = new Voucher();
        $voucher4->setExpiryDate(new \DateTimeImmutable('yesterday'));
        $voucher4->setInvoice($invoice);
        $user->addVoucher($voucher4);

        $validVouchers = $user->getValidVouchers();

        self::assertCount(1, $validVouchers);
        self::assertContains($voucher1, $validVouchers);
    }
}
