<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\InvoiceFixtures;
use App\DataFixtures\PriceFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Manager\VoucherManager;
use App\Repository\InvoiceRepository;
use App\Repository\PriceRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherRepository;
use App\Repository\VoucherTypeRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class VoucherManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    protected ?AbstractDatabaseTool $databaseTool;
    private ?User $user;
    private ?Price $singlePrice;
    private ?VoucherType $voucherType;
    private ?Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseTool = static::getContainer()
                                    ->get(DatabaseToolCollection::class)
                                    ->get()
        ;

        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
            PriceFixtures::class,
            InvoiceFixtures::class,
        ]);

        $this->user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $this->singlePrice = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrice();
        $this->voucherType = static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['units' => '10']);
        $this->invoice     = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => InvoiceFixtures::VOUCHER_INVOICE_NUMBER]);
    }

    public function testCreateVouchersGeneratesVouchers(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager(), static::getContainer()->get(VoucherTypeRepository::class));

        $voucherManager->createVouchersForInvoice($this->user, $this->voucherType, $this->invoice, $this->singlePrice->getAmount());
        $vouchers = static::getContainer()->get(VoucherRepository::class)->findBy([
            'user'    => $this->user,
            'invoice' => $this->invoice,
        ]);
        self::assertCount(10, $vouchers);
        self::assertContainsOnlyInstancesOf(Voucher::class, $vouchers);
    }

    public function testCreateVouchersSetsADifferentCodeForAllVouchers(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager(), static::getContainer()->get(VoucherTypeRepository::class));

        $voucherManager->createVouchersForInvoice($this->user, $this->voucherType, $this->invoice, $this->singlePrice->getAmount());
        $vouchers = static::getContainer()->get(VoucherRepository::class)->findBy([
            'user'    => $this->user,
            'invoice' => $this->invoice,
        ]);
        $codes = [];
        foreach ($vouchers as $voucher) {
            $codes[] = $voucher->getCode();
        }

        self::assertCount(10, array_unique($codes));
    }

    public function testCreateVouchersSetsTheCorrectExpirationDate(): void
    {
        $now = new \DateTimeImmutable('2024-03-01');
        static::mockTime($now);
        $voucherManager = new VoucherManager(static::getContainer()->get('doctrine')->getManager(), static::getContainer()->get(VoucherTypeRepository::class));

        $voucherManager->createVouchersForInvoice($this->user, $this->voucherType, $this->invoice, $this->singlePrice->getAmount());
        $vouchers = static::getContainer()->get(VoucherRepository::class)->findBy([
            'user'    => $this->user,
            'invoice' => $this->invoice,
        ]);
        $expirationDate = $now->modify('+' . $this->voucherType->getValidityMonths() . ' months');
        self::assertSame($expirationDate->format('Y-m-d'), $vouchers[0]->getExpiryDate()->format('Y-m-d'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
        $this->user         = null;
        $this->singlePrice  = null;
        $this->voucherType  = null;
        $this->invoice      = null;
    }
}
