<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\DataFixtures\BookingWithPaymentFixture;
use App\DataFixtures\InvoiceFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Payment;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\PayPalService;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvoicePayPalPaymentControllerTest extends WebTestCase
{
    public function testDownloadInvoiceLogsErrorAndRedirectsWhenInvoiceIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/invoice/' . BookingPaymentControllerTest::FAKE_UUID . '/paypal';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/user');
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

    public function testPayInvoiceWithPayPalRedirectsWhenBookingInvoiceIsAlreadyPaid(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithPaymentFixture::INVOICE_NUMBER]);
        $client->request('GET', '/invoice/' . $invoice->getUuid() . '/paypal');

        static::assertResponseRedirects('/booking/' . $invoice->getBookings()->first()->getUuid() . '/payment/confirmation');
    }

    public function testPayInvoiceWithPayPalRedirectsWhenOtherInvoiceIsAlreadyPaid(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([InvoiceFixtures::class, VoucherFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => 'CO202400321']);
        $client->request('GET', '/invoice/' . $invoice->getUuid() . '/paypal');

        static::assertResponseRedirects('/user');
    }

    public function testCapturePayPalPaymentAndReturnsTargetUrlWhenNoInvoiceExists(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $uri = '/invoice/' . BookingPaymentControllerTest::FAKE_UUID . '/paypal/capture';
        $client->request('POST', $uri);

        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Invoice not found.', $data['error']);
        self::assertSame('/user', $data['targetUrl']);
    }

    public function testCapturePayPalPaymentReturnsTargetUrlWhenBookingInvoiceIsAlreadyPaid(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithPaymentFixture::INVOICE_NUMBER]);
        $client->request('POST', '/invoice/' . $invoice->getUuid() . '/paypal/capture');
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('/booking/' . $invoice->getBookings()->first()->getUuid() . '/payment/confirmation', $data['targetUrl']);
    }

    public function testCapturePayPalPaymentChecksPayload(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);

        $uri     = '/invoice/' . $invoice->getUuid() . '/paypal/capture';
        $client->request('POST', $uri);

        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Payload is empty.', $data['error']);
        self::assertSame('/invoice/' . $invoice->getUuid() . '/paypal', $data['targetUrl']);
    }

    public function testCapturePayPalPaymentReturnsTargetUrlWhenCaptureIsNotSuccessfull(): void
    {
        $client            = static::createClient();
        $mockPaypalService = $this->getMockBuilder(PayPalService::class)
                                  ->disableOriginalConstructor()
                                  ->onlyMethods(['handlePayment'])
                                  ->getMock();

        $mockPaypalService->method('handlePayment')->willReturn(false);

        static::getContainer()->set(PayPalService::class, $mockPaypalService);

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);

        $client->request(
            'POST',
            '/invoice/' . $invoice->getUuid() . '/paypal/capture',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['data' => ['orderID' => '123456']])
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Payment has not been processed.', $data['error']);
        self::assertSame('/invoice/' . $invoice->getUuid() . '/paypal', $data['targetUrl']);
    }

    public function testCapturePayPalPaymentCreatesPaymentAndReturnsTargetUrlWhenCaptureIsSuccessfull(): void
    {
        $client            = static::createClient();
        $mockPaypalService = $this->getMockBuilder(PayPalService::class)
                                  ->disableOriginalConstructor()
                                  ->onlyMethods(['handlePayment'])
                                  ->getMock();

        $mockPaypalService->method('handlePayment')->willReturn(true);

        static::getContainer()->set(PayPalService::class, $mockPaypalService);

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        $client->request(
            'POST',
            '/invoice/' . $invoice->getUuid() . '/paypal/capture',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['data' => ['orderID' => '123456']])
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertResponseStatusCodeSame(Response::HTTP_OK);

        $paymentRepository = static::getContainer()->get(PaymentRepository::class);
        $payment           = $paymentRepository->findOneBy(['invoice' => $invoice, 'type' => Payment::PAYMENT_TYPE_PAYPAL]);
        self::assertNotNull($payment);
        self::assertSame('Payment has been processed.', $data['success']);
        self::assertSame('/booking/' . $invoice->getBookings()->first()->getUuid() . '/payment/confirmation', $data['targetUrl']);
    }
}
