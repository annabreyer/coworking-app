<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\PriceRepository;
use App\Repository\RoomRepository;
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
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $invoice = static::getContainer()
                          ->get(InvoiceRepository::class)
                          ->findOneBy(['number' => InvoiceFixtures::BOOKING_INVOICE_NUMBER]);
        $bookings = $invoice->getBookings();
        foreach ($bookings as $booking) {
            $invoice->removeBooking($booking);
        }
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice must have at least one booking');

        $invoiceGenerator = $this->getInvoiceGenerator();
        $invoiceGenerator->generateBookingInvoice($invoice);
    }

    public function testGenerateBookingInvoiceThrowsExceptionIfInvoiceHasSeveralBookings(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
            'App\DataFixtures\InvoiceFixtures',
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

        $invoice = static::getContainer()
                          ->get(InvoiceRepository::class)
                          ->findOneBy(['number' => InvoiceFixtures::BOOKING_INVOICE_NUMBER]);
        $invoice->addBooking($booking);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only one invoice per booking');

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

    public function testGenerateVoucherInvoiceThrowsExceptionIfPriceIsNoVoucherPrice(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $singlePrice      = static::getContainer()->get(PriceRepository::class)->findActiveUnitaryPrice();
        $invoice          = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => InvoiceFixtures::VOUCHER_INVOICE_NUMBER]);
        $invoiceGenerator = $this->getInvoiceGenerator();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Price must be a voucher.');
        $invoiceGenerator->generateVoucherInvoice($invoice, $singlePrice);
    }

    public function testGenerateVoucherInvoiceThrowsExceptionIfPriceHasNoVoucherType(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $voucherPrice = new Price();
        $voucherPrice->setAmount(1000)
                     ->setIsActive(true)
                     ->setIsVoucher(true)
        ;

        $invoice          = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => InvoiceFixtures::VOUCHER_INVOICE_NUMBER]);
        $invoiceGenerator = $this->getInvoiceGenerator();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Price must have a voucher type.');

        $invoiceGenerator->generateVoucherInvoice($invoice, $voucherPrice);
    }

    public function testGenerateVoucherInvoiceThrowsExceptionIfInvoiceVoucherCountDoesNotMatchVoucherType(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
        ]);

        $voucherPrice     = static::getContainer()->get(PriceRepository::class)->findActiveVoucherPrices()[0];
        $invoice          = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => InvoiceFixtures::VOUCHER_INVOICE_NUMBER]);
        $invoiceGenerator = $this->getInvoiceGenerator();

        $voucherPrice->getVoucherType()->setUnits(2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher count does not match voucher type.');

        $invoiceGenerator->generateVoucherInvoice($invoice, $voucherPrice);
    }

    public function testGetTargetDirectoryIsComposedOfYearAndMonth(): void
    {
        $invoice = static::getContainer()
                          ->get(InvoiceRepository::class)
                          ->findOneBy(['number' => InvoiceFixtures::BOOKING_INVOICE_NUMBER]);

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
