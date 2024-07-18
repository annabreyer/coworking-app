<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
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

    public function createVouchers(User $user, VoucherType $voucherType, Invoice $invoice, ?int $unitaryValue = null): void
    {
        if (null === $unitaryValue) {
            $unitaryValue = $voucherType->getUnitaryValue();
        }
        $vouchers       = $voucherType->getUnits();
        $validityMonths = $voucherType->getValidityMonths() ?? 0;

        for ($i = 0; $i < $vouchers; ++$i) {
            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode($this->generateVoucherCode());
            $voucher->setExpiryDate($this->calculateExpiryDate($validityMonths));
            $voucher->setValue($unitaryValue);
            $this->entityManager->persist($voucher);

            $invoice->addVoucher($voucher);
        }

        $this->entityManager->flush();
    }

    private function calculateExpiryDate(int $validityMonths): \DateTimeImmutable
    {
        return $this->now()->modify('+' . $validityMonths . ' months');
    }
}
