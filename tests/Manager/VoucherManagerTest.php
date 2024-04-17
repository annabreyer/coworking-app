<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\Voucher;
use App\Manager\VoucherManager;
use App\Repository\PriceRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherTypeRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class VoucherManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    protected ?AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseTool = static::getContainer()
                                    ->get(DatabaseToolCollection::class)
                                    ->get()
        ;
    }

    public function testCreateVouchersGeneratesVouchers(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\VoucherFixtures',
            'App\DataFixtures\PriceFixtures',
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $singlePrice = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];
        $voucherType = static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['units' => '10']);

        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager());

        $vouchers = $voucherManager->createVouchers($user, $voucherType, $singlePrice->getAmount());
        self::assertCount(10, $vouchers);
        self::assertContainsOnlyInstancesOf(Voucher::class, $vouchers);
    }

    public function testCreateVouchersSetsADifferentCodeForAllVouchers(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\VoucherFixtures',
            'App\DataFixtures\PriceFixtures',
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $singlePrice = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];
        $voucherType = static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['units' => '10']);

        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager());

        $vouchers = $voucherManager->createVouchers($user, $voucherType, $singlePrice->getAmount());
        $codes    = [];
        foreach ($vouchers as $voucher) {
            $codes[] = $voucher->getCode();
        }

        self::assertCount(10, array_unique($codes));
    }

    public function testCreateVouchersSetsTheCorrectExpirationDate(): void
    {
        $now = new \DateTimeImmutable('2024-03-01');
        static::mockTime($now);
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\VoucherFixtures',
            'App\DataFixtures\PriceFixtures',
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $singlePrice = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];
        $voucherType = static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['units' => '10']);

        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager());

        $vouchers       = $voucherManager->createVouchers($user, $voucherType, $singlePrice->getAmount());
        $expirationDate = $now->modify('+' . $voucherType->getValidityMonths() . ' months');
        self::assertSame($expirationDate->format('Y-m-d'), $vouchers[0]->getExpiryDate()->format('Y-m-d'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
