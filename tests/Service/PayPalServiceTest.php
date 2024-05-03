<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\Repository\InvoiceRepository;
use App\Service\PayPalService;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPalServiceTest extends WebTestCase
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

    public function testGetQueryParametersForJsSdk(): void
    {
        self::bootKernel();
        $container     = static::getContainer();
        $payPalService = $container->get(PayPalService::class);

        $expected = 'client-id=123456789&commit=true&components=buttons&currency=EUR&debug=true&integration-date=2024-04-29&intent=authorize&disable-funding=credit%2Ccard%2Cgiropay%2Csepa&locale=de_DE';
        static::assertSame($expected, $payPalService->getQueryParametersForJsSdk());
    }

    public function testHandlePaymentLogsErrorForMissingPayPalOrderId(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $container     = static::getContainer();
        $payPalService = $container->get(PayPalService::class);

        $requestData = ['orderID' => ''];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderId is empty.',
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenHttpStatusCodeIsNot200(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId     = '123456789';
        $orderResponseBody = $this->getMockOrderClientResponseBody($payPalOrderId);
        $payPalService     = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_OK,
            $orderResponseBody,
            json_encode([])
        );
        $requestData = ['orderID' => $payPalOrderId];

        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'GetPaypalOrderData returned Status Code: ' . Response::HTTP_BAD_REQUEST . ' PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenResponseBodyIsEmpty(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            json_encode([]),
            json_encode([])
        );
        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderData is empty. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenIntentIsNotCapture(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $body          = $this->getMockOrderClientResponseBody($payPalOrderId, PayPalService::INTENT_AUTHORIZE);
        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $body,
            json_encode([])
        );
        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderData error. Expected CAPTURE, got: AUTHORIZE. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenStatusIsNotApproved(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $body          = $this->getMockOrderClientResponseBody(
            $payPalOrderId,
            PayPalService::INTENT_CAPTURE,
            PayPalService::STATUS_COMPLETED
        );
        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $body,
            json_encode([])
        );
        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderData error. Expected APPROVED, got: COMPLETED. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenCurrencyIsWrong(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $body          = $this->getMockOrderClientResponseBody(
            $payPalOrderId,
            PayPalService::INTENT_CAPTURE,
            PayPalService::STATUS_APPROVED,
            '15.00',
            'USD'
        );

        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $body,
            json_encode([])
        );
        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderData error. Expected EUR, got: USD. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenAmountIsWrong(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $body          = $this->getMockOrderClientResponseBody($payPalOrderId);
        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $body,
            json_encode([])
        );

        $requestData = ['orderID' => $payPalOrderId];
        $invoice->setAmount(1000);

        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalOrderData error. Expected 10, got: 15.00. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenCapturePaymentHttpStatusCodeIsNot200(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId       = '123456789';
        $orderResponseBody   = $this->getMockOrderClientResponseBody($payPalOrderId);
        $paymentResponseBody = $this->getMockPaymentClientResponseBody();

        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            $orderResponseBody,
            $paymentResponseBody
        );

        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }

        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'PayPalCapturePayment returned Status Code ' . Response::HTTP_BAD_REQUEST . '. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenCapturePaymentResponseBodyIsEmpty(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId     = '123456789';
        $orderResponseBody = $this->getMockOrderClientResponseBody($payPalOrderId);
        $payPalService     = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $orderResponseBody,
            json_encode([])
        );

        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }

        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'Capture response is empty. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsFalseWhenCapturePaymentStatusIsNotCompleted(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId       = '123456789';
        $orderResponseBody   = $this->getMockOrderClientResponseBody($payPalOrderId);
        $paymentResponseBody = $this->getMockPaymentClientResponseBody(PayPalService::STATUS_APPROVED);
        $payPalService       = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $orderResponseBody,
            $paymentResponseBody
        );

        $requestData = ['orderID' => $payPalOrderId];
        static::assertFalse($payPalService->handlePayment($invoice, $requestData));

        $logger = static::getContainer()->get('monolog.logger');
        static::assertNotNull($logger);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }

        static::assertNotNull($testHandler);
        static::assertTrue($testHandler->hasRecordThatContains(
            'CapturePayment error. Expected COMPLETED, got: APPROVED. PayPalOrderId: ' . $payPalOrderId,
            Level::fromName('error')
        ));
    }

    public function testHandlePaymentReturnsTrueWhenCapturePaymentIsSuccessful(): void
    {
        self::bootKernel();
        $this->databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithInvoiceNoPaymentFixture::INVOICE_NUMBER]);
        static::assertNotNull($invoice);

        $payPalOrderId = '123456789';
        $payPalService = $this->getPayPalService(
            Response::HTTP_OK,
            Response::HTTP_OK,
            Response::HTTP_OK,
            $this->getMockOrderClientResponseBody($payPalOrderId),
            $this->getMockPaymentClientResponseBody()
        );

        $requestData = ['orderID' => $payPalOrderId];
        static::assertTrue($payPalService->handlePayment($invoice, $requestData));
    }

    private function getPayPalService(
        int $authHttpCode,
        int $orderHttpCode,
        int $paymentHttpCode,
        string $orderResponseBody,
        string $paymentResponseBody
    ): PayPalService {
        $clientId = '987654321';
        $debug    = 'true';
        $endpoint = 'https://i-do-not-exist.com';

        return new PayPalService(
            $clientId,
            $debug,
            $endpoint,
            $this->getMockAuthHttpClient($authHttpCode),
            $this->getMockOrderClient($orderHttpCode, $orderResponseBody),
            $this->getMockPaymentClient($paymentHttpCode, $paymentResponseBody),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    private function getMockAuthHttpClient(int $expectedStatusCode): HttpClientInterface
    {
        $body = json_encode([
            'access_token' => 'A21AAFEpH4PsADK7qSS7pSRsgzfENtu-Q1ysgEDVDESseMHBYXVJYE8ovjj68elIDy8nF26AwPhfXTIeWAZHSLIsQkSYz9ifg',
            'token_type'   => 'Bearer',
            'expires_in'   => 32400,
        ]);
        $info             = ['http_code' => $expectedStatusCode];
        $authMockResponse = new MockResponse($body, $info);
        $mockHttpClient   = new MockHttpClient($authMockResponse);

        return $mockHttpClient;
    }

    private function getMockOrderClient(int $expectedStatusCode, string|iterable $body): HttpClientInterface
    {
        $info              = ['http_code' => $expectedStatusCode];
        $orderMockResponse = new MockResponse($body, $info);
        $mockHttpClient    = new MockHttpClient($orderMockResponse);

        return $mockHttpClient;
    }

    private function getMockOrderClientResponseBody(
        string $id,
        string $intent = PayPalService::INTENT_CAPTURE,
        string $status = PayPalService::STATUS_APPROVED,
        string $amount = '15.00',
        string $currency = 'EUR'
    ): string {
        return json_encode(
            [
                'id'             => $id,
                'status'         => $status,
                'intent'         => $intent,
                'payment_source' => [
                    'paypal' => [
                        'name' => [
                            'given_name' => 'John',
                            'surname'    => 'Doe',
                        ],
                        'email_address' => 'customer@example.com',
                        'account_id'    => 'QYR5Z8XDVJNXQ',
                    ],
                ],
                'purchase_units' => [
                    [
                        'reference_id' => 'd9f80740-38f0-11e8-b467-0ed5f89f718b',
                        'amount'       => [
                            'currency_code' => $currency,
                            'value'         => $amount,
                        ],
                    ],
                ],
            ]
        );
    }

    private function getMockPaymentClient(int $expectedStatusCode, string|iterable $body): HttpClientInterface
    {
        $info                = ['http_code' => $expectedStatusCode];
        $paymentMockResponse = new MockResponse($body, $info);
        $mockHttpClient      = new MockHttpClient($paymentMockResponse);

        return $mockHttpClient;
    }

    private function getMockPaymentClientResponseBody(string $status = PayPalService::STATUS_COMPLETED): string
    {
        return json_encode([
            'id'     => '2GG279541U471931P',
            'status' => $status,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
