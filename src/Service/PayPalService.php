<?php

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
    private const ERROR_MESSAGE = 'PayPalOrderData error. Expected %s, got: %s. PayPalOrderId: %s';
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
            'client-id'        => $this->clientId,
            'commit'           => 'true',
            // Show a 'Pay Now' button
            'components'       => 'buttons',
            'currency'         => 'EUR',
            'debug'            => $this->debug,
            'integration-date' => '2024-04-29',
            // Do not update this date, it ensures backward-compat
            'intent'           => 'authorize',
            //capture later via http client
            'disable-funding'  => 'credit,card,giropay,sepa',
            // Disables buttons, see https://developer.paypal.com/sdk/js/configuration/#link-disablefunding
            'locale'           => 'de_DE',
        ];

        return http_build_query($payPalParameters);
    }

    public function handlePayment(Invoice $invoice, array $requestData): bool
    {
        $paypalOrderId = $requestData['orderID'];

        if (empty($paypalOrderId)) {
            $this->logger->error('PayPalOrderId is empty.');

            return false;
        }

        $responseBody = $this->orderClient->request(
            'GET',
            $this->getGetEndpointUrl($paypalOrderId), [
            'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken(),],
        ]);

        if (Response::HTTP_OK !== $responseBody->getStatusCode()) {
            $this->logger->error('GetPaypalOrderData returned Status Code: '.$responseBody->getStatusCode(). ' PayPalOrderId: ' . $paypalOrderId);

            return false;
        }

        $paypalOrderData = $responseBody->toArray();

        if (empty($paypalOrderData)) {
            $this->logger->error('PayPalOrderData is empty. PayPalOrderId: ' . $paypalOrderId);

            return false;
        }

        if ($paypalOrderData['intent'] !== self::INTENT_CAPTURE) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, self::INTENT_CAPTURE, $paypalOrderData['intent'], $paypalOrderId));

            return false;
        }

        if ($paypalOrderData['status'] !== self::STATUS_APPROVED) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, self::STATUS_APPROVED, $paypalOrderData['status'], $paypalOrderId));

            return false;
        }

        if ('EUR' !== $paypalOrderData['purchase_units'][0]['amount']['currency_code']) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, 'EUR', $paypalOrderData['purchase_units'][0]['amount']['currency_code'], $paypalOrderData['id']));

            return false;
        }
        $amount = $invoice->getAmount()/100;
        if ($amount !== (int)$paypalOrderData['purchase_units'][0]['amount']['value']) {
            $this->logger->error(sprintf(self::ERROR_MESSAGE, $amount, $paypalOrderData['purchase_units'][0]['amount']['value'], $paypalOrderData['id']));

            return false;
        }

        if ($this->capturePayment($paypalOrderId, $invoice)) {
            $this->logger->info('PayPalOrderId: ' . $paypalOrderId . ' was successfully captured.');

            return true;
        }

        $this->logger->error('PayPalOrderId: ' . $paypalOrderId . ' could not be captured.');
        return false;
    }

    private function getAccessToken(): string
    {
        if (null !== $this->accessToken) {
            return $this->accessToken;
        }

        $responseBody = $this->authClient->request(
            'POST',
            $this->getAuthEndpointUrl(), ['body' => 'grant_type=client_credentials']);

        $this->accessToken = $responseBody->toArray()['access_token'];

        return $this->accessToken;
    }

    private function capturePayment(string $paypalOrderId, Invoice $invoice): bool
    {
        $responseBody = $this->paymentClient->request(
            'POST',
            $this->getCaptureEndpointUrl($paypalOrderId), ['headers' => [
                'Authorization'     => 'Bearer ' . $this->getAccessToken(),
                'Paypal-Request-Id' => $invoice->getUuid(),
            ],
        ]);


        if (Response::HTTP_OK !== $responseBody->getStatusCode()) {
            $this->logger->error('PayPalCapturePayment returned Status Code '.$responseBody->getStatusCode(). '. PayPalOrderId: ' . $paypalOrderId);

            return false;
        }

        $response  = $responseBody->toArray();

        if (empty($response)) {
            $this->logger->error('Capture response is empty. PayPalOrderId: ' . $paypalOrderId);

            return false;
        }


        if (false === \array_key_exists('status', $response) || $response['status'] !== self::STATUS_COMPLETED) {
            $this->logger->error(sprintf('CapturePayment error. Expected %s, got: %s. PayPalOrderId: %s', self::STATUS_COMPLETED, $response['status'], $paypalOrderId));

            return false;
        }

        return true;
    }

    private function getAuthEndpointUrl(): string
    {
        return sprintf('%s/v1/oauth2/token', $this->endpoint);
    }

    private function getGetEndpointUrl($id): string
    {
        return sprintf('%s/v2/checkout/orders/%s', $this->endpoint, $id);
    }

    private function getCaptureEndpointUrl($id): string
    {
        return sprintf('%s/v2/checkout/orders/%s/capture', $this->endpoint, $id);
    }
}