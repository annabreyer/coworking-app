<?php

declare(strict_types=1);

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
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoicePaypalPaymentController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly PayPalService $payPalService,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceManager $invoiceManager,
        private readonly PaymentManager $paymentManager,
    ) {
    }

    #[Route('/invoice/{uuid}/paypal', name: 'invoice_payment_paypal')]
    public function payInvoiceWithPayPal(string $uuid,): Response
    {
        $invoice = null;
        try {
            $invoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
        }

        if (null === $invoice) {
            $this->addFlash('error', $this->translator->trans('invoice_payment.not_found', [], 'flash'));

            return $this->redirectToRoute('user_dashboard');
        }

        if ($invoice->isFullyPaid()) {
            $this->addFlash('success', $this->translator->trans('invoice_payment.already_paid', [], 'flash'));

            return $this->redirectToRoute('invoice_payment_confirmation', ['uuid' => $invoice->getUuid()]);
        }

        if (null !== $invoice->getPayPalOrderId()) {
            $request = new Request();
            $request->setMethod('POST');
            $request->request->set('data', ['orderID' => $invoice->getPayPalOrderId()]);

            return $this->capturePayPalPayment($invoice->getUuid()->__toString(), $request);
        }

        return $this->render('invoice/paypal_payment.html.twig', [
            'invoice'    => $invoice,
            'parameters' => $this->payPalService->getQueryParametersForJsSdk(),
        ]);
    }

    #[Route('/invoice/{uuid}/paypal/capture', name: 'invoice_payment_paypal_capture', methods: ['POST'])]
    public function capturePayPalPayment(string $uuid, Request $request): Response
    {
        $invoice = null;
        try {
            $invoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
        }

        if (null === $invoice) {
            $targetUrl = $this->generateUrl('user_dashboard');

            return $this->json(
                ['error' => 'Invoice not found.', 'targetUrl' => $targetUrl],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($invoice->isFullyPaid()) {
            $this->addFlash('success', $this->translator->trans('invoice_payment.already_paid', [], 'flash'));
            $targetUrl = $this->generateUrl('invoice_payment_confirmation', ['uuid' => $invoice->getUuid()]);

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
        $this->invoiceManager->saveInvoice();

        $targetUrl = $this->generateUrl('invoice_payment_confirmation', ['uuid' => $invoice->getUuid()]);

        if (false === $this->payPalService->handlePayment($invoice)) {
            $this->addFlash('error', $this->translator->trans('invoice_payment.payment_error', [], 'flash'));

            return $this->json(
                ['error' => 'Payment has not been processed.', 'targetUrl' => $targetUrl],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->paymentManager->finalizePaypalPayment($invoice);

        if ($invoice->isVoucherInvoice()) {
            $this->invoiceManager->generateVoucherInvoicePdf($invoice);
            $this->invoiceManager->sendVoucherInvoiceToUser($invoice);
        }

        if ($invoice->isBookingInvoice()) {
            $this->invoiceManager->generateBookingInvoicePdf($invoice);
            $this->invoiceManager->sendBookingInvoiceToUser($invoice);

            if ($request->getSession()->has(BookingPaymentController::BOOKING_STEP_PAYMENT)) {
                $request->getSession()->remove(BookingPaymentController::BOOKING_STEP_PAYMENT);
                $targetUrl = $this->generateUrl('booking_payment_confirmation', ['uuid' => $invoice->getFirstBooking()->getUuid()]);
            }
        }

        $this->invoiceManager->sendInvoiceToDocumentVault($invoice);

        return $this->json(
            ['success' => 'Payment has been processed.', 'targetUrl' => $targetUrl],
            Response::HTTP_OK
        );
    }

    #[Route('/invoice/{uuid}/confirmation', name: 'invoice_payment_confirmation', methods: ['GET'])]
    public function confirmPayment(string $uuid): Response
    {
        $invoice = null;
        try {
            $invoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $this->addFlash('error', $this->translator->trans('invoice_payment.not_found', [], 'flash'));
        }

        return $this->render('invoice/confirmation.html.twig', [
            'invoice' => $invoice,
            'isError' => null === $invoice || false === $invoice->isFullyPaid(),
        ]);
    }
}
