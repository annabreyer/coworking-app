<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class VoucherManager
{
    use ClockAwareTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function generateVoucherCode(): string
    {
        return mb_strtoupper(bin2hex(random_bytes(5)));
    }

    public static function calculateExpiryDate(\DateTimeInterface $now, int $validityMonths): \DateTimeImmutable
    {
        return $now->modify('+' . $validityMonths . ' months');
    }

    public static function createVouchers(User $user, VoucherType $voucherType, int $quantity, int $unitaryValue, \DateTimeInterface $expiryDate): ArrayCollection
    {
        $vouchers = new ArrayCollection();

        for ($i = 0; $i < $quantity; ++$i) {
            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode(static::generateVoucherCode());
            $voucher->setExpiryDate($expiryDate);
            $voucher->setValue($unitaryValue);

            $vouchers->add($voucher);
        }

        return $vouchers;
    }

    public function createVouchersForInvoice(User $user, VoucherType $voucherType, Invoice $invoice, ?int $unitaryValue = null): void
    {
        if (null === $unitaryValue) {
            $unitaryValue = $voucherType->getUnitaryValue();
        }

        $vouchers        = $voucherType->getUnits();
        $validityMonths  = $voucherType->getValidityMonths() ?? 0;
        $expiryDate      = static::calculateExpiryDate($this->now(), $validityMonths);
        $vouchers        = static::createVouchers($user, $voucherType, $vouchers, $unitaryValue, $expiryDate);
        $invoiceVouchers = $invoice->getVouchers();

        $allVouchers = new ArrayCollection(array_merge($invoiceVouchers->toArray(), $vouchers->toArray()->toArray()));

        $invoice->setVouchers($allVouchers);

        $this->entityManager->flush();
    }
}
