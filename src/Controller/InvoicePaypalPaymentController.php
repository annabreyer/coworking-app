<?php declare(strict_types = 1);

namespace App\Controller;

use App\Manager\InvoiceManager;
use App\Manager\PaymentManager;
use App\Repository\InvoiceRepository;
use App\Service\PayPalService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvoicePaypalPaymentController extends AbstractController
{
    public function __construct(
        private readonly PayPalService $payPalService,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceManager $invoiceManager,
        private readonly PaymentManager $paymentManager,
        private readonly LoggerInterface $logger,
    ) {
    }
    #[Route('/invoice/{uuid}/paypal', name: 'invoice_payment_paypal')]
    public function payInvoiceWithPayPal(
        string $uuid,
    ): Response {
        try {
            $invoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $invoice = null;
        }

        if (null === $invoice) {
            $this->addFlash('error', 'Invoice not found.');
            return $this->redirectToRoute('user_show');
        }

        if ($invoice->isFullyPaid()){
            $this->addFlash('success', 'Invoice has already been paid.');

            if ($invoice->isBookingInvoice()){
                return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $invoice->getBookings()->first()->getUuid()]);
            }

            return $this->redirectToRoute('user_show');
        }

        if (null !== $invoice->getPayPalOrderId()){
            $request = new Request();
            $request->setMethod('POST');
            $request->request->set('data', ['orderID' => $invoice->getPayPalOrderId()]);

            return $this->capturePayPalPayment($invoice->getUuid()->__toString(), $request);
        }

        return $this->render('invoice/paypal_payment.html.twig', [
            'invoice'     => $invoice,
            'parameters'  => $this->payPalService->getQueryParametersForJsSdk(),
        ]);
    }

    #[Route('/invoice/{uuid}/paypal/capture', name: 'invoice_payment_paypal_capture', methods: ['POST'])]
    public function capturePayPalPayment(string $uuid, Request $request,): Response
    {
        try {
            $invoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $invoice = null;
        }

        if (null === $invoice) {
            $targetUrl = $this->generateUrl('user_show');

            return $this->json(
                ['error' => 'Invoice not found.', 'targetUrl' => $targetUrl],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($invoice->isFullyPaid()) {
            $targetUrl = $this->generateUrl('user_show');

            if ($invoice->isBookingInvoice()){
                $targetUrl = $this->generateUrl('booking_payment_confirmation', ['uuid' => $invoice->getBookings()->first()->getUuid()]);
            }
            return $this->json(
                ['error' => 'Invoice has already been paid.', 'targetUrl' => $targetUrl],
                Response::HTTP_BAD_REQUEST
            );
        }

        $payload = $request->getPayload()->all();
        if (empty($payload) || empty($payload['data']['orderID'])) {
            $this->logger->error('Payload is empty. Invoice No. ' . $invoice->getNumber());
            $targetUrl = $this->generateUrl('invoice_payment_paypal', ['uuid' => $invoice->getUuid()]);

            return $this->json(['error' => 'Payload is empty.', 'targetUrl' => $targetUrl], Response::HTTP_BAD_REQUEST);
        }

        $invoice->setPaypalOrderId($payload['data']['orderID']);

        if ($this->payPalService->handlePayment($invoice)) {
            $this->paymentManager->finalizePaypalPayment($invoice);
            $this->invoiceManager->sendInvoiceToDocumentVault($invoice);

            if ($invoice->isVoucherInvoice()){
                $this->invoiceManager->generateVoucherInvoicePdf($invoice);
                $this->invoiceManager->sendVoucherInvoiceToUser($invoice);
                $targetUrl = $this->generateUrl('user_vouchers', ['uuid' => $invoice->getUuid()]);
            }

            if ($invoice->isBookingInvoice()){
                $this->invoiceManager->generateBookingInvoicePdf($invoice);
                $this->invoiceManager->sendBookingInvoiceToUser($invoice);
                $targetUrl = $this->generateUrl('booking_payment_confirmation', ['uuid' => $invoice->getBookings()->first()->getUuid()]);
            }

            return $this->json(
                ['success' => 'Payment has been processed.', 'targetUrl' => $targetUrl],
                Response::HTTP_OK
            );
        }

        $targetUrl = $this->generateUrl('invoice_payment_paypal', ['uuid' => $invoice->getUuid()]);

        return $this->json(
            ['error' => 'Payment has not been processed.', 'targetUrl' => $targetUrl],
            Response::HTTP_BAD_REQUEST
        );
    }
}
