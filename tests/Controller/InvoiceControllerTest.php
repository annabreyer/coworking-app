<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\DataFixtures\InvoiceFixtures;
use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class InvoiceControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    protected ?AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDownloadInvoiceLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/invoice/'.BookingPaymentControllerTest::FAKE_UUID.'/download';
        $client->request('GET', $uri);

        $this->assertResponseRedirects('/');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        self::assertNotNull($testHandler);
        self::assertTrue($testHandler->hasRecordThatContains(
            'Invoice not found.',
            Level::fromName('error')
        ));
        self::assertTrue($testHandler->hasRecordThatContains(
            BookingPaymentControllerTest::FAKE_UUID,
            Level::fromName('error')
        ));
    }

    public function testDownloadInvoiceChecksUser()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER]);

        $uri = '/invoice/' . $invoice->getUuid() . '/download';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDownloadInvoiceGeneratesInvoiceIfNotExists()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $invoiceUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($invoiceUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER]);

        $uri = '/invoice/' . $invoice->getUuid() . '/download';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath         = $invoiceGenerator->getTargetDirectory($invoice);
        self::assertFileExists($filePath);
    }

    public function testDownloadInvoiceReturnsPdfResponse()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);


        $userRepository = static::getContainer()->get(UserRepository::class);
        $invoiceUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($invoiceUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER]);

        $uri = '/invoice/' . $invoice->getUuid() . '/download';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
