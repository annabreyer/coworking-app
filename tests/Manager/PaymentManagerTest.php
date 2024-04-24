<?php declare(strict_types = 1);

namespace App\Tests\Manager;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Payment;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\PaymentManager;
use App\Repository\BusinessDayRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherRepository;
use App\Service\AdminMailerService;
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

    public function testHandleVoucherPaymentCreatesPayment(): void
    {
        $this->databaseTool->loadFixtures([
            AppFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $booking = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice = static::getContainer()->get(InvoiceManager::class)->createInvoiceFromBooking($booking, 2000);

        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => 'VO20240001']);
        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(AdminMailerService::class)
        );

        $paymentManager->handleVoucherPayment($invoice, $voucher);

        $this->assertNotNull($invoice->getPayments()->first());
        $this->assertSame(Payment::PAYMENT_TYPE_VOUCHER, $invoice->getPayments()->first()->getType());
    }

    public function testHandleVoucherPaymentSetsVoucherUseDate(): void
    {
        $this->databaseTool->loadFixtures([
            AppFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $booking = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice = static::getContainer()->get(InvoiceManager::class)->createInvoiceFromBooking($booking, 2000);

        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => 'VO20240001']);
        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(AdminMailerService::class)
        );
        $paymentManager->handleVoucherPayment($invoice, $voucher);

        $this->assertNotNull($voucher->getUseDate());
    }
    public function testHandleVoucherPaymentUpdatesInvoiceAmount(): void
    {
        $this->databaseTool->loadFixtures([
            AppFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $booking = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice = static::getContainer()->get(InvoiceManager::class)->createInvoiceFromBooking($booking, 2000);

        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => 'VO20240001']);
        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::createMock(AdminMailerService::class)
        );

        $paymentManager->handleVoucherPayment($invoice, $voucher);

        $this->assertEquals(500, $invoice->getAmount());
    }


    public function testHandleVoucherSendsAdminEmailWhenInvoiceIsNegative(): void
    {
        $this->databaseTool->loadFixtures([
            AppFixtures::class,
            VoucherFixtures::class,
        ]);

        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable('2024-04-22')]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $booking = static::getContainer()->get(BookingManager::class)->saveBooking($user, $businessDay, $room);
        $invoice = static::getContainer()->get(InvoiceManager::class)->createInvoiceFromBooking($booking, 1000);

        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => 'VO20240001']);
        $paymentManager = new PaymentManager(
            static::getContainer()->get('doctrine.orm.entity_manager'),
            static::getContainer()->get(AdminMailerService::class)
        );

        $paymentManager->handleVoucherPayment($invoice, $voucher);

        $this->assertEmailCount(1);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}