<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingWithOutInvoiceFixture;
use App\DataFixtures\InvoiceFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Manager\InvoiceManager;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Service\AdminMailerService;
use App\Service\InvoiceGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Filesystem\Filesystem;
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

    public function testGetInvoiceNumberIncrementsExistingInvoiceWithDifferentYear(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-01-01'));
        $this->databaseTool->loadFixtures([InvoiceFixtures::class]);

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $prefix         = self::getContainer()->getParameter('invoice_prefix');
        // Only one fixture for 2023
        $expectedNumber = $prefix . '20230002';

        self::assertSame($expectedNumber, $invoiceNumber);
    }

    public function testGetInvoiceNumberStartsWithOneIfNoInvoicesExist(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([BasicFixtures::class]);

        $invoiceManager = $this->getInvoiceManager();
        $invoiceNumber  = $invoiceManager->getInvoiceNumber();
        $prefix         = self::getContainer()->getParameter('invoice_prefix');
        $expectedNumber = $prefix . date('Y') . '0001';

        self::assertSame($expectedNumber, $invoiceNumber);
    }

    public function testGetInvoiceNumberIncrementsExistingInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([BasicFixtures::class]);
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

    public function testCreateAndSaveInvoiceFromBookingThrowsExceptionIfBookingHasNoUser(): void
    {
        $bookingMock    = $this->createMock(Booking::class);
        $invoiceManager = $this->getInvoiceManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking must have a user.');
        $invoiceManager->createAndSaveInvoiceFromBooking($bookingMock, PriceFixtures::SINGLE_PRICE_AMOUNT);
    }

    public function testCreateAndSaveInvoiceFromBookingThrowsExceptionIfBookingAlreadyHasOne(): void
    {
        $bookingMock = $this->createMock(Booking::class);
        $bookingMock->method('getInvoice')
                    ->willReturn(new Invoice());
        $bookingMock->method('getUser')
            ->willReturn(new User());

        $invoiceManager = $this->getInvoiceManager();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Booking already has an invoice.');
        $invoiceManager->createAndSaveInvoiceFromBooking($bookingMock, PriceFixtures::SINGLE_PRICE_AMOUNT);
    }

    public function testCreateAndSaveInvoiceFromBookingCreatesAndReturnsInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $date        = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $user,
                             ])
        ;

        $invoiceManager = $this->getInvoiceManager();
        $invoice        = $invoiceManager->createAndSaveInvoiceFromBooking($booking, PriceFixtures::SINGLE_PRICE_AMOUNT);
        self::assertNotNull($invoice);

        self::assertSame($booking->getUser(), $invoice->getUser());
        self::assertSame(PriceFixtures::SINGLE_PRICE_AMOUNT, $invoice->getAmount());
        self::assertSame($booking, $invoice->getBookings()->first());
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasNoUser(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection());

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a user.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasAmountZero(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection());
        $invoiceMock->method('getUser')
            ->willReturn(new User());
        $invoiceMock->method('getAmount')
            ->willReturn(0);

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive amount to generate a reversal invoice.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasNegativeAmount(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection());
        $invoiceMock->method('getUser')
                    ->willReturn(new User());
        $invoiceMock->method('getAmount')
                    ->willReturn(-100);

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have a positive amount to generate a reversal invoice.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasNoPayment(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection());
        $invoiceMock->method('getUser')
                    ->willReturn(new User());
        $invoiceMock->method('getAmount')
                    ->willReturn(100);

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have exactly one refund payment to generate a reversal invoice.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasPaymentOtherThanRefund(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([(new Payment())->setType(Payment::PAYMENT_TYPE_VOUCHER)]));
        $invoiceMock->method('getUser')
                    ->willReturn(new User());
        $invoiceMock->method('getAmount')
                    ->willReturn(100);

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have exactly one refund payment to generate a reversal invoice.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceThrowsExceptionIfInvoiceHasMoreThanOneRefundPayment(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([
                        (new Payment())->setType(Payment::PAYMENT_TYPE_REFUND),
                        (new Payment())->setType(Payment::PAYMENT_TYPE_REFUND),
                    ]));
        $invoiceMock->method('getUser')
                    ->willReturn(new User());
        $invoiceMock->method('getAmount')
                    ->willReturn(100);

        $invoiceManager = $this->getInvoiceManager();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invoice must have exactly one refund payment to generate a reversal invoice.');
        $invoiceManager->createReversalInvoice($invoiceMock);
    }

    public function testCreateReversalInvoiceCreatesInvoiceWithNegativeAmountOfOriginalInvoice(): void
    {
        $invoicePaymentMock = $this->createMock(Payment::class);
        $invoicePaymentMock->method('getType')
            ->willReturn(Payment::PAYMENT_TYPE_REFUND);
        $invoicePaymentMock->method('getComment')
            ->willReturn('description');
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([$invoicePaymentMock]))
        ;
        $invoiceMock->method('getUser')
                    ->willReturn(new User())
        ;
        $invoiceMock->method('getAmount')
                    ->willReturn(100)
        ;

        $invoiceManager  = $this->getInvoiceManager();
        $reversalInvoice = $invoiceManager->createReversalInvoice($invoiceMock);

        self::assertSame(-100, $reversalInvoice->getAmount());
    }

    public function testCreateReversalInvoiceCreatesInvoiceWithDescriptionSameAsRefundPaymentCommentOfOriginalInvoice(): void
    {
        $invoicePaymentMock = $this->createMock(Payment::class);
        $invoicePaymentMock->method('getType')
                           ->willReturn(Payment::PAYMENT_TYPE_REFUND);
        $invoicePaymentMock->method('getComment')
                           ->willReturn('This is a payment comment.');
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getPayments')
                    ->willReturn(new ArrayCollection([$invoicePaymentMock]))
        ;
        $invoiceMock->method('getUser')
                    ->willReturn(new User())
        ;
        $invoiceMock->method('getAmount')
                    ->willReturn(100)
        ;

        $invoiceManager  = $this->getInvoiceManager();
        $reversalInvoice = $invoiceManager->createReversalInvoice($invoiceMock);

        self::assertSame('This is a payment comment.', $reversalInvoice->getDescription());
    }

    public function testProcessReversalInvoice(): void
    {
        $originalInvoice = new Invoice();
        $reversalInvoice = new Invoice();

        $invoiceManagerMock = $this->getMockBuilder(InvoiceManager::class)
                                   ->onlyMethods(['createReversalInvoice', 'saveInvoice', 'generateInvoicePdf'])
                                   ->disableOriginalConstructor()
                                   ->getMock()
        ;

        $invoiceManagerMock->expects(self::once())
                           ->method('createReversalInvoice')
                           ->with($originalInvoice)
                           ->willReturn($reversalInvoice)
        ;

        $invoiceManagerMock->expects(self::once())
                           ->method('saveInvoice')
                           ->with($reversalInvoice)
        ;

        $invoiceManagerMock->expects(self::once())
                           ->method('generateInvoicePdf')
                           ->with($reversalInvoice)
        ;

        $invoiceManagerMock->processReversalInvoice($originalInvoice);
    }

    public function testGenerateInvoiceGeneratesBookingInvoiceIfInvoiceIsBookingInvoice(): void
    {
        $bookingInvoiceMock = $this->createMock(Invoice::class);
        $bookingInvoiceMock
            ->method('isBookingInvoice')
            ->willReturn(true)
        ;

        $mockInvoiceGenerator = $this->createMock(InvoiceGenerator::class);
        $mockInvoiceGenerator->expects(self::once())
                             ->method('generateBookingInvoice')
                             ->with($bookingInvoiceMock);

        $invoiceManager = $this->getInvoiceManager($mockInvoiceGenerator);
        $invoiceManager->generateInvoicePdf($bookingInvoiceMock);
    }

    public function testGenerateInvoiceGeneratesVoucherInvoiceIfInvoiceIsVoucherInvoice(): void
    {
        $voucherInvoiceMock = $this->createMock(Invoice::class);
        $voucherInvoiceMock
            ->method('isVoucherInvoice')
            ->willReturn(true)
        ;

        $mockInvoiceGenerator = $this->createMock(InvoiceGenerator::class);
        $mockInvoiceGenerator->expects(self::once())
                             ->method('generateVoucherInvoice')
                             ->with($voucherInvoiceMock);

        $invoiceManager = $this->getInvoiceManager($mockInvoiceGenerator);
        $invoiceManager->generateInvoicePdf($voucherInvoiceMock);
    }

    public function testGenerateInvoiceGeneratesGeneralInvoiceIfInvoiceIsNeitherBookingNorVoucher(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock
            ->method('isVoucherInvoice')
            ->willReturn(false);
        $invoiceMock
            ->method('isBookingInvoice')
            ->willReturn(false)
        ;

        $mockInvoiceGenerator = $this->createMock(InvoiceGenerator::class);
        $mockInvoiceGenerator->expects(self::once())
                             ->method('generateGeneralInvoice')
                             ->with($invoiceMock);

        $invoiceManager = $this->getInvoiceManager($mockInvoiceGenerator);
        $invoiceManager->generateInvoicePdf($invoiceMock);
    }

    public function testRegenerateInvoicePdfRemovesFileIfInvoiceHasAFileName(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock
            ->method('getFilePath')
            ->willReturn('filepath');

        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->expects(self::once())
                      ->method('remove')
                      ->with('filepath');

        $invoiceManager = $this->getInvoiceManager(null, $mockFilesystem);
        $invoiceManager->regenerateInvoicePdf($invoiceMock);
    }

    public function testRegenerateInvoicePdfCallsGenerateInvoicePdf(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);

        $invoiceManagerMock = $this->getMockBuilder(InvoiceManager::class)
                                   ->onlyMethods(['generateInvoicePdf'])
                                   ->disableOriginalConstructor()
                                   ->getMock()
        ;
        $invoiceManagerMock->expects(self::once())
                           ->method('generateInvoicePdf')
                           ->with($invoiceMock)
        ;

        $invoiceManagerMock->regenerateInvoicePdf($invoiceMock);
    }

    private function getInvoiceManager(?InvoiceGenerator $invoiceGenerator = null, ?Filesystem $filesystem = null): InvoiceManager
    {
        if (null === $invoiceGenerator) {
            $invoiceGenerator = $this->createMock(InvoiceGenerator::class);
        }

        if (null === $filesystem) {
            $filesystem = $this->createMock(Filesystem::class);
        }

        $entityManager      = self::getContainer()->get('doctrine')->getManager();
        $mockTranslator     = $this->createMock(TranslatorInterface::class);
        $invoicePrefix      = self::getContainer()->getParameter('invoice_prefix');
        $invoiceRepository  = self::getContainer()->get(InvoiceRepository::class);
        $adminMailerService = self::getContainer()->get(AdminMailerService::class);

        return new InvoiceManager(
            $invoiceGenerator,
            $entityManager,
            $mockTranslator,
            $invoiceRepository,
            $filesystem,
            $adminMailerService,
            $invoicePrefix,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
