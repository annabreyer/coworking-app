<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\DataFixtures\AppFixtures;
use App\Entity\Invoice;
use App\Manager\InvoiceManager;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\PriceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Service\InvoiceGenerator;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceManagerTest extends KernelTestCase
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

    public function testGetClientNumberAddsZerosToMatchLength(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $invoiceManager = $this->getInvoiceManager();

        self::assertSame('00001', $invoiceManager::getClientNumber(1));
        self::assertSame('00111', $invoiceManager::getClientNumber(111));
        self::assertSame('11111', $invoiceManager::getClientNumber(11111));
    }

    public function testCreateInvoiceFromBookingThrowsExceptionIfBookingHasNoUser(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\PriceFixtures',
        ]);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                             ])
        ;

        $price = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrice();

        $booking->setUser(null);
        $invoiceManager = $this->getInvoiceManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking must have a user');
        $invoiceManager->createInvoiceFromBooking($booking, $price->getAmount());
    }

    public function testCreateInvoiceFromBookingReturnsInvoiceIfBookingAlreadyHasOne(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);
        $userRepository = static::getContainer()->get(UserRepository::class);
        $invoiceUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date           = new \DateTimeImmutable('2024-04-01');
        $businessDay    = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room           = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking        = static::getContainer()->get(BookingRepository::class)
                                ->findOneBy([
                                    'room'        => $room,
                                    'businessDay' => $businessDay,
                                    'user'        => $invoiceUser,
                                ])
        ;

        $price          = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrice();
        $invoiceManager = $this->getInvoiceManager();
        $invoice        = $invoiceManager->createInvoiceFromBooking($booking, $price->getAmount());

        self::assertSame($invoice, $booking->getInvoice());
    }

    public function testCreateInvoiceFromBookingCreatesAndReturnsInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\PriceFixtures',
        ]);

        $date        = new \DateTimeImmutable('2024-04-02');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 1']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                             ])
        ;

        $price          = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrice();
        $invoiceManager = $this->getInvoiceManager();
        $invoice        = $invoiceManager->createInvoiceFromBooking($booking, $price->getAmount());

        self::assertSame($booking->getUser(), $invoice->getUser());
        self::assertSame($price->getAmount(), $invoice->getAmount());
        self::assertSame($booking, $invoice->getBookings()->first());
    }

    public function testGetInvoiceNumberStartsWithOneIfNoInvoicesExist(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $prefix         = self::getContainer()->getParameter('invoice_prefix');
        $expectedNumber = $prefix . date('Y') . '0001';

        self::assertSame($expectedNumber, $invoiceNumber);
    }

    public function testGetInvoiceNumberIncrementsExistingInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([AppFixtures::class]);
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);

        $invoice = new Invoice();
        $invoice->setNumber('CO20241000');
        $invoice->setDate(new \DateTimeImmutable('2024-03-01'));
        $invoice->setAmount(100);
        $invoice->setUser($user);

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($invoice);
        $entityManager->flush();

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();

        $prefix         = self::getContainer()->getParameter('invoice_prefix');
        $expectedNumber = $prefix . date('Y') . '1001';

        self::assertSame($expectedNumber, $invoiceNumber);
    }

    public function testGetInvoiceNumberIncrementsExistingInvoiceWithDifferentYear(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-01-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $prefix         = self::getContainer()->getParameter('invoice_prefix');
        // Only one fixture for 2023
        $expectedNumber = $prefix . '20230002';

        self::assertSame($expectedNumber, $invoiceNumber);
    }

    private function getInvoiceManager(): InvoiceManager
    {
        $entityManager        = self::getContainer()->get('doctrine')->getManager();
        $mockInvoiceGenerator = $this->createMock(InvoiceGenerator::class);
        $mockMailer           = $this->createMock(MailerInterface::class);
        $mockTranslator       = $this->createMock(TranslatorInterface::class);
        $mockUrlGenerator     = $this->createMock(UrlGeneratorInterface::class);
        $invoicePrefix        = self::getContainer()->getParameter('invoice_prefix');
        $invoiceRepository    = self::getContainer()->get(InvoiceRepository::class);

        return new InvoiceManager(
            $mockInvoiceGenerator,
            $entityManager,
            $mockMailer,
            $mockTranslator,
            $mockUrlGenerator,
            $invoiceRepository,
            $invoicePrefix,
            'documentVaultEmail'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
