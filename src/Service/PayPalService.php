<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPalService
{
    public const INTENT_AUTHORIZE = 'AUTHORIZE';
    public const INTENT_CAPTURE   = 'CAPTURE';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_COMPLETED = 'COMPLETED';
    private const ERROR_MESSAGE   = 'PayPalOrderData error. Expected %s, got: %s. PayPalOrderId: %s';
    private ?string $accessToken;

    public function __construct(
        private readonly string $clientId,
        private readonly string $debug,
        private readonly string $endpoint,
        private readonly HttpClientInterface $authClient,
        private readonly HttpClientInterface $orderClient,
        private readonly HttpClientInterface $paymentClient,
        private readonly LoggerInterface $logger
    ) {
        $this->accessToken = null;
    }

    public function getQueryParametersForJsSdk(): string
    {
        $payPalParameters = [
            'client-id' => $this->clientId,
            'commit'    => 'true',
            // Show a 'Pay Now' button
            'components'       => 'buttons',
            'currency'         => 'EUR',
            'debug'            => $this->debug,
            'integration-date' => '2024-04-29',
            // Do not update this date, it ensures backward-compat
            'intent' => 'capture',
            // capture later via http client
            'disable-funding' => 'credit,card,giropay,sepa',
            // Disables buttons, see https://developer.paypal.com/sdk/js/configuration/#link-disablefunding
            'locale' => 'de_DE',
        ];

        return http_build_query($payPalParameters);
    }

    public function handlePayment(Invoice $invoice): bool
    {
        if (null === $invoice->getPayPalOrderId()) {
            $this->logger->error('PayPalOrderId is empty.');

            return false;
        }

        $responseBody = $this->orderClient->request(
            'GET',
            sprintf('%s/v2/checkout/orders/%s', $this->endpoint, $invoice->getPayPalOrderId()),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ],
            ]
        );

        if (Response::HTTP_OK !== $responseBody->getStatusCode()) {
            $this->logger->error('GetPaypalOrderData returned Status Code: ' . $responseBody->getStatusCode() . ' PayPalOrderId: ' . $invoice->getPayPalOrderId());

            return false;
        }

        $paypalOrderData = $responseBody->toArray();

        if (empty($paypalOrderData)) {
            $this->logger->error('PayPalOrderData is empty. PayPalOrderId: ' . $invoice->getPayPalOrderId());

            return false;
        }

        if (false === $this->validatePaypalOrderData($invoice, $paypalOrderData)) {
            return false;
        }

        if ($this->capturePayment($invoice)) {
            $this->logger->info('PayPalOrderId: ' . $invoice->getPayPalOrderId() . ' was successfully captured.');

            return true;
        }

        $this->logger->error('PayPalOrderId: ' . $invoice->getPayPalOrderId() . ' could not be captured.');

        return false;
    }

    private function getAccessToken(): string
    {
        if (null !== $this->accessToken) {
            return $this->accessToken;
        }

        $responseBody = $this->authClient->request(
            'POST',
            sprintf('%s/v1/oauth2/token', $this->endpoint),
            ['body' => 'grant_type=client_credentials']
        );

        $this->accessToken = $responseBody->toArray()['access_token'];

        return $this->accessToken;
    }

    private function capturePayment(Invoice $invoice): bool
    {
        $responseBody = $this->paymentClient->request(
            'POST',
            sprintf('%s/v2/checkout/orders/%s/capture', $this->endpoint, $invoice->getPayPalOrderId()),
            [
                'headers' => [
                    'Authorization'     => 'Bearer ' . $this->getAccessToken(),
                    'Paypal-Request-Id' => $invoice->getUuid(),
                ],
            ]
        );

        if (Response::HTTP_CREATED !== $responseBody->getStatusCode()) {
            $this->logger->error('PayPalCapturePayment returned Status Code ' . $responseBody->getStatusCode() . '. PayPalOrderId: ' . $invoice->getPayPalOrderId());

            return false;
        }

        $response = $responseBody->toArray();

        if (empty($response)) {
            $this->logger->error('Capture response is empty. PayPalOrderId: ' . $invoice->getPayPalOrderId());

            return false;
        }

        if (false === \array_key_exists('status', $response) || self::STATUS_COMPLETED !== $response['status']) {
            $this->logger->error(sprintf('CapturePayment error. Expected %s, got: %s. PayPalOrderId: %s', self::STATUS_COMPLETED, $response['status'], $invoice->getPayPalOrderId()));

            return false;
        }

        return true;
    }

    /**
     * @param array<mixed> $paypalOrderData
     */
    private function validatePaypalOrderData(Invoice $invoice, array $paypalOrderData): bool
    {
        if (self::INTENT_CAPTURE !== $paypalOrderData['intent']) {
            $this->logger->error(sprintf(
                self::ERROR_MESSAGE,
                self::INTENT_CAPTURE,
                $paypalOrderData['intent'],
                $invoice->getPayPalOrderId()
            ));

            return false;
        }

        if (self::STATUS_APPROVED !== $paypalOrderData['status']) {
            $this->logger->error(sprintf(
                self::ERROR_MESSAGE,
                self::STATUS_APPROVED,
                $paypalOrderData['status'],
                $invoice->getPayPalOrderId()
            ));

            return false;
        }

        $paypalCurrency = $paypalOrderData['purchase_units'][0]['amount']['currency_code'];
        if ('EUR' !== $paypalCurrency) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, 'EUR', $paypalCurrency, $invoice->getPayPalOrderId()));

            return false;
        }

        $amount       = $invoice->getAmount() / 100;
        $payPalAmount = (int) $paypalOrderData['purchase_units'][0]['amount']['value'];
        if ($amount !== $payPalAmount) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, $amount, $payPalAmount, $invoice->getPayPalOrderId()));

            return false;
        }

        return true;
    }
}
