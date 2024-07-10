<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class InvoiceControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testDownloadInvoiceLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/invoice/' . BookingPaymentControllerTest::FAKE_UUID . '/download';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);
        $testHandler = null;

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

    public function testDownloadInvoiceChecksUser(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.two@annabreyer.dev']);
        $client->loginUser($testUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER]);

        $uri = '/invoice/' . $invoice->getUuid() . '/download';
        $client->request('GET', $uri);

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDownloadInvoiceGeneratesInvoiceIfNotExists(): void
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

        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath         = $invoiceGenerator->getTargetDirectory($invoice);
        self::assertFileExists($filePath);
    }

    public function testDownloadInvoiceReturnsPdfResponse(): void
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

        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        static::assertResponseHeaderSame('content-type', 'application/pdf');
    }
}
