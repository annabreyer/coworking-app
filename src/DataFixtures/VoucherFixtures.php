<?php declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Price;
use App\Entity\Voucher;
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
        ];
    }

    public function load(ObjectManager $manager)
    {
        $user        = $this->getReference('user1');
        $voucherType = $this->getReference('voucherType10Units');
        $singlePrice = $this->getReference('price-single', Price::class);

        $vouchers        = $voucherType->getUnits();
        $createdVouchers = new ArrayCollection();
        for ($i = 0; $i < $vouchers; $i++) {
            $now        = new \DateTimeImmutable('2024-04-04');
            $expiryDate = $now->modify('+' . $voucherType->getValidityMonths() . ' months');

            $voucher = new Voucher();
            $voucher->setUser($user);
            $voucher->setVoucherType($voucherType);
            $voucher->setCode(VoucherManager::generateVoucherCode());
            $voucher->setExpiryDate($expiryDate);
            $voucher->setValue($singlePrice->getAmount());
            $manager->persist($voucher);

            $createdVouchers[] = $voucher;

        }
        $manager->flush();
    }
}

