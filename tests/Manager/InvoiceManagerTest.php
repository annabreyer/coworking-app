<?php

declare(strict_types=1);

namespace App\Tests\Manager;

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

        $price = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];

        $booking->setUser(null);
        $invoiceManager = $this->getInvoiceManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking must have a user');
        $invoiceManager->createInvoiceFromBooking($booking, $price);
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

        $price          = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];
        $invoiceManager = $this->getInvoiceManager();
        $invoice        = $invoiceManager->createInvoiceFromBooking($booking, $price);

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

        $price          = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrices()[0];
        $invoiceManager = $this->getInvoiceManager();
        $invoice        = $invoiceManager->createInvoiceFromBooking($booking, $price);

        self::assertSame($booking->getUser(), $invoice->getUser());
        self::assertSame($price->getAmount(), $invoice->getAmount());
        self::assertSame($booking, $invoice->getBookings()->first());
    }

    public function testGetInvoiceNumberReturnsStartingNumberIfNoInvoicesExist(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $startingNumber = self::getContainer()->getParameter('invoice_starting_number');

        self::assertSame(date('Y') . $startingNumber, $invoiceNumber);
    }

    public function testGetInvoiceNumberIncrementsExistingInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $invoice        = static::getContainer()->get(InvoiceRepository::class)->findOneBy([], ['number' => 'DESC']);

        self::assertSame($invoice->getNumber(), $invoiceNumber);
    }

    private function getInvoiceManager(): InvoiceManager
    {
        $entityManager        = self::getContainer()->get('doctrine')->getManager();
        $mockInvoiceGenerator = $this->createMock(InvoiceGenerator::class);
        $mockMailer           = $this->createMock(MailerInterface::class);
        $mockTranslator       = $this->createMock(TranslatorInterface::class);
        $mockUrlGenerator     = $this->createMock(UrlGeneratorInterface::class);
        $startingNumber       = self::getContainer()->getParameter('invoice_starting_number');

        return new InvoiceManager(
            $mockInvoiceGenerator,
            $entityManager,
            $mockMailer,
            $mockTranslator,
            $mockUrlGenerator,
            $startingNumber
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
