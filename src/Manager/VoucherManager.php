<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    public function createVouchers(User $user, VoucherType $voucherType, int $unitaryValue): Collection
    {
        $vouchers        = $voucherType->getUnits();
        $createdVouchers = new ArrayCollection();
        for ($i = 0; $i < $vouchers; ++$i) {
            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode($this->generateVoucherCode());
            $voucher->setExpiryDate($this->calculateExpiryDate($voucherType->getValidityMonths()));
            $voucher->setValue($unitaryValue);
            $this->entityManager->persist($voucher);

            $createdVouchers[] = $voucher;
        }
        $this->entityManager->flush();

        return $createdVouchers;
    }

    private function calculateExpiryDate(int $validityMonths): \DateTimeImmutable
    {
        return $this->now()->modify('+' . $validityMonths . ' months');
    }
}
