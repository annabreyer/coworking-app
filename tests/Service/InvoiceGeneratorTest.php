<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\DataFixtures\BookingWithOutInvoiceFixture;
use App\DataFixtures\InvoiceFixtures;
use App\Entity\Invoice;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Service\InvoiceGenerator;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceGeneratorTest extends KernelTestCase
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

    public function testGenerateBookingInvoiceThrowsExceptionIfInvoiceIsNotPersisted(): void
    {
        $invoice = new Invoice();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must be persisted.');

        $invoiceGenerator = $this->getInvoiceGenerator();
        $invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function testGenerateBookingInvoiceThrowsExceptionIfInvoiceHasNoBooking(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                          ->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        $bookings = $invoice->getBookings();

        foreach ($bookings as $booking) {
            $invoice->removeBooking($booking);
        }
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must have exactly one booking');

        $invoiceGenerator = $this->getInvoiceGenerator();
        $invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function testGenerateBookingInvoiceThrowsExceptionIfInvoiceHasSeveralBookings(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class, BookingWithInvoiceNoPaymentFixture::class]);

        $date        = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $user        = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);

        $booking = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $user,
                             ])
        ;

        self::assertNotNull($booking);

        $invoice = static::getContainer()
                          ->get(InvoiceRepository::class)
                          ->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);

        self::assertNotNull($invoice);
        $invoice->addBooking($booking);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must have exactly one booking.');

        $invoiceGenerator = $this->getInvoiceGenerator();
        $invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function testGenerateVoucherInvoiceThrowsExceptionIfInvoiceIsNotPersisted(): void
    {
        $invoice = new Invoice();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must be persisted.');

        $invoiceGenerator = $this->getInvoiceGenerator();
        $invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function testGetTargetDirectoryIsComposedOfYearAndMonth(): void
    {
        $this->databaseTool->loadFixtures([
            InvoiceFixtures::class,
        ]);
        $invoice = static::getContainer()
                          ->get(InvoiceRepository::class)
                          ->findOneBy(['number' => InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER]);

        $invoiceGenerator = $this->getInvoiceGenerator();
        $expectedPath     = 'invoiceDirectory/2024/03';
        self::assertSame($expectedPath, $invoiceGenerator->getTargetDirectory($invoice));
    }

    private function getInvoiceGenerator(): InvoiceGenerator
    {
        $mockTranslator = $this->createMock(TranslatorInterface::class);
        $mockFilesystem = $this->createMock(Filesystem::class);

        return new InvoiceGenerator(
            $mockTranslator,
            $mockFilesystem,
            'invoiceTemplatePath',
            'invoiceDirectory',
            'invoiceClientNumberPrefix'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
