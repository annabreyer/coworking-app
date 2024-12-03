<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingWithPaymentFixture;
use App\DataFixtures\InvoiceFixtures;
use App\DataFixtures\PriceFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Invoice;
use App\Entity\Payment;
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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
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
    protected bool $bootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->bootKernel) {
            $this->bootKernelAndLoadFixtures();
        }
    }

    private function bootKernelAndLoadFixtures(): void
    {
        self::bootKernel();
        $this->databaseTool = static::getContainer()
                                    ->get(DatabaseToolCollection::class)
                                    ->get();


        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
            PriceFixtures::class,
            InvoiceFixtures::class,
            BookingWithPaymentFixture::class,
        ]);

        $this->initializeCommonEntities();
    }

    private function initializeCommonEntities(): void
    {
        $container = static::getContainer();

        $this->user        = $container->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $this->singlePrice = $container->get(PriceRepository::class)->findActiveUnitaryPrice();
        $this->voucherType = $container->get(VoucherTypeRepository::class)->findOneBy(['units' => '10']);
        $this->invoice     = $container->get(InvoiceRepository::class)->findOneBy(['number' => InvoiceFixtures::VOUCHER_INVOICE_NUMBER]);
    }

    private function ensureKernelIsBooted(): void
    {
        if (!$this->databaseTool) {
            self::bootKernel();
            $this->bootKernelAndLoadFixtures();
        }
    }

    public function testCreateVouchersGeneratesVouchers(): void
    {
        $this->ensureKernelIsBooted();

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
        $this->ensureKernelIsBooted();

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
        $this->ensureKernelIsBooted();

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

    public function testCreateRefundVoucherThrowsExceptionWhenInvoiceHasNoPayment(): void
    {
        $voucherManager = new VoucherManager(
            $this->createMock(
                EntityManagerInterface::class
            ),
            $this->createMock(VoucherTypeRepository::class)
        );

        $invoiceMock = $this->createMock(Invoice::class);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a payment to be refunded.');
        $voucherManager->createRefundVoucher($invoiceMock);
    }

    public function testCreateRefundVoucherThrowsExceptionWhenInvoiceHasNoUser(): void
    {
        $this->bootKernel = false;

        $voucherManager = new VoucherManager(
            $this->createMock(
                EntityManagerInterface::class
            ),
            $this->createMock(VoucherTypeRepository::class)
        );

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
            ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_TRANSACTION)]));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a user.');
        $voucherManager->createRefundVoucher($invoiceMock);
    }

    public function testCreateRefundVoucherThrowsExceptionWhenPaidAmountIsZero(): void
    {
        $this->bootKernel = false;

        $voucherManager = new VoucherManager(
            $this->createMock(
                EntityManagerInterface::class
            ),
            $this->createMock(VoucherTypeRepository::class)
        );

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
            ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_TRANSACTION)]));
        $invoiceMock->method('getUser')
            ->willReturn($this->createMock(User::class));
        $invoiceMock->method('getPaidAmount')
            ->willReturn(0);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have least been partly paid.');
        $voucherManager->createRefundVoucher($invoiceMock);
    }

    public function testCreateRefundVoucherThrowsExceptionWhenThereIsNoVoucherTypeRefundInTheDatabase(): void
    {
        $this->bootKernel = false;

        $voucherTypeRepository = $this->createMock(VoucherTypeRepository::class);
        $voucherTypeRepository->method('findOneBy')
            ->withAnyParameters()
            ->willReturn(null);
        $voucherManager = new VoucherManager(
            $this->createMock(
                EntityManagerInterface::class
            ),
            $voucherTypeRepository
        );

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
            ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_TRANSACTION)]));
        $invoiceMock->method('getUser')
                    ->willReturn($this->createMock(User::class));
        $invoiceMock->method('getPaidAmount')
                    ->willReturn(100);

        self::expectException(\Exception::class);
        self::expectExceptionMessage('There is no voucher with name REFUND in the database.');
        $voucherManager->createRefundVoucher($invoiceMock);
    }

    public function testCreateRefundVoucherSetsVoucherInvoiceWithRefundedInvoice(): void
    {
        $this->ensureKernelIsBooted();

        /** @var VoucherManager $voucherManager */
        $voucherManager = self::getContainer()->get(VoucherManager::class);
        $invoice        = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithPaymentFixture::INVOICE_NUMBER]);

        $refundVoucher = $voucherManager->createRefundVoucher($invoice);
        self::assertSame($invoice, $refundVoucher->getInvoice());
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
