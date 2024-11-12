<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Repository\VoucherTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class VoucherManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VoucherTypeRepository $voucherTypeRepository,
    ) {
    }

    public static function generateVoucherCode(): string
    {
        return mb_strtoupper(bin2hex(random_bytes(5)));
    }

    public static function calculateExpiryDate(\DateTimeInterface $startingDate, int $validityMonths): \DateTimeImmutable
    {
        if (false === $startingDate instanceof \DateTimeImmutable) {
            $startingDate = new \DateTimeImmutable($startingDate->format('Y-m-d H:i:s'));
        }

        return $startingDate->modify('+' . $validityMonths . ' months');
    }

    public static function createVouchers(User $user, VoucherType $voucherType, int $quantity, int $unitaryValue, \DateTimeInterface $expiryDate, ?Invoice $invoice = null): ArrayCollection
    {
        $vouchers = new ArrayCollection();

        for ($i = 0; $i < $quantity; ++$i) {
            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode(static::generateVoucherCode());
            $voucher->setExpiryDate($expiryDate);
            $voucher->setValue($unitaryValue);

            if (null !== $invoice) {
                $invoice->addVoucher($voucher);
            }

            $vouchers->add($voucher);
        }

        return $vouchers;
    }

    public function createVouchersForInvoice(User $user, VoucherType $voucherType, Invoice $invoice, ?int $unitaryValue = null): void
    {
        if (null === $unitaryValue) {
            $unitaryValue = (int) $voucherType->getUnitaryValue();
        }

        $quantity       = (int) $voucherType->getUnits();
        $validityMonths = $voucherType->getValidityMonths() ?? 0;
        $expiryDate     = static::calculateExpiryDate($this->now(), $validityMonths);
        static::createVouchers($user, $voucherType, $quantity, $unitaryValue, $expiryDate, $invoice);

        $this->entityManager->flush();
    }

    public function createRefundVoucher(User $user, int $unitaryValue, ?\DateTimeInterface $expiryDate = null): Voucher
    {
        $voucherType = $this->voucherTypeRepository->findOneBy(['name' => VoucherType::NAME_REFUND]);
        if (null === $voucherType) {
            throw new \RuntimeException('Refund voucher type not found.');
        }

        if (null === $expiryDate) {
            $expiryDate = static::calculateExpiryDate($this->now(), (int) $voucherType->getValidityMonths());
        }

        $voucher = static::createVouchers($user, $voucherType, 1, $unitaryValue, $expiryDate)[0];
        $this->entityManager->persist($voucher);
        $this->entityManager->flush();

        return $voucher;
    }
}
