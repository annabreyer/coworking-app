<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
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

    public function testDownloadInvoiceChecksUser()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $invoiceUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $invoice     = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['user' => $invoiceUser]);

        $uri = '/invoice/' . $invoice->getUuid() . '/download';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDownloadInvoiceGeneratesInvoiceIfNotExists()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $invoiceUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($invoiceUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['user' => $invoiceUser]);

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

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $invoiceUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($invoiceUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['user' => $invoiceUser]);

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
