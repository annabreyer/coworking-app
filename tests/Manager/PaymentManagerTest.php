<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Voucher;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\PaymentManager;
use App\Manager\VoucherManager;
use App\Repository\BusinessDayRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentManagerTest extends KernelTestCase
{
    protected ?AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseTool = static::getContainer()
                                    ->get(DatabaseToolCollection::class)
                                    ->get()
        ;
    }

    public function testCreateVoucherPaymentThrowsExceptionWhenVoucherValueIsZero(): void
    {
        $voucherMock = $this->createMock(Voucher::class);
        $voucherMock->method('getValue')->willReturn(0);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Voucher must have a positive value to be used as payment.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->createVoucherPayment($voucherMock);
    }

    public function testCreateVoucherPaymentThrowsExceptionWhenVoucherValueIsNegative(): void
    {
        $voucherMock = $this->createMock(Voucher::class);
        $voucherMock->method('getValue')->willReturn(-100);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Voucher must have a positive value to be used as payment.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->createVoucherPayment($voucherMock);
    }

    public function testCreateVoucherPaymentThrowsExceptionWhenWhenVoucherHasNoAmount(): void
    {
        $voucherMock = $this->createMock(Voucher::class);
        $voucherMock->method('getValue')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Voucher must have a positive value to be used as payment.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->createVoucherPayment($voucherMock);
    }

    public function testHandleInvoicePaymentWithVoucherThrowsExceptionWhenInvoiceHasNoValue(): void
    {
        $mockInvoice = $this->createMock(Invoice::class);
        $mockInvoice->method('getAmount')->willReturn(null);

        $mockVoucher = $this->createMock(Voucher::class);
        $mockVoucher->method('getValue')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invoice must have a positive amount to be paid.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->handleInvoicePaymentWithVoucher($mockInvoice, $mockVoucher);
    }

    public function testHandleInvoicePaymentWithVoucherThrowsExceptionWhenVoucherHasNoValue(): void
    {
        $mockInvoice = $this->createMock(Invoice::class);
        $mockInvoice->method('getAmount')->willReturn(100);

        $mockVoucher = $this->createMock(Voucher::class);
        $mockVoucher->method('getValue')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Voucher must have a positive value in order to be used as payment.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->handleInvoicePaymentWithVoucher($mockInvoice, $mockVoucher);
    }

    public function testHandleInvoicePaymentWithVoucherCreatesPayment(): void
    {
        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $booking     = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice     = static::getContainer()->get(InvoiceManager::class)->createAndSaveInvoiceFromBooking($booking, 2000);
        $voucher     = $user->getVouchers()->first();

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->handleInvoicePaymentWithVoucher($invoice, $voucher);

        self::assertNotNull($invoice->getPayments()->first());
        self::assertSame(Payment::PAYMENT_TYPE_VOUCHER, $invoice->getPayments()->first()->getType());
    }

    public function testHandleInvoicePaymentWithVoucherSetsVoucherUseDate(): void
    {
        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $booking     = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice     = static::getContainer()->get(InvoiceManager::class)->createAndSaveInvoiceFromBooking($booking, 2000);
        $voucher     = $user->getVouchers()->first();

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::getContainer()->get(VoucherManager::class)
        );
        $paymentManager->handleInvoicePaymentWithVoucher($invoice, $voucher);

        self::assertNotNull($voucher->getUseDate());
    }

    public function testHandleInvoicePaymentWithVoucherUpdatesInvoiceAmount(): void
    {
        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $booking     = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice     = static::getContainer()->get(InvoiceManager::class)->createAndSaveInvoiceFromBooking($booking, 2000);
        $voucher     = $user->getVouchers()->first();

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::getContainer()->get(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->handleInvoicePaymentWithVoucher($invoice, $voucher);

        self::assertSame(500, $invoice->getAmount());
    }

    public function testHandleVoucherSendsAdminEmailWhenInvoiceIsNegative(): void
    {
        $this->databaseTool->loadFixtures([
            BasicFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $booking     = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice     = static::getContainer()->get(InvoiceManager::class)->createAndSaveInvoiceFromBooking($booking, 1000);
        $voucher     = $user->getVouchers()->first();

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::getContainer()->get(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->handleInvoicePaymentWithVoucher($invoice, $voucher);

        static::assertEmailCount(1);
    }

    public function testFinalizePaypalPaymentThrowsExceptionWhenInvoiceHasNoValue(): void
    {
        $mockInvoice = $this->createMock(Invoice::class);
        $mockInvoice->method('getAmount')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice must have an amount.');

        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(InvoiceManager::class),
            static::createMock(VoucherManager::class)
        );

        $paymentManager->finalizePaypalPayment($mockInvoice);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
