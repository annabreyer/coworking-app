<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Price;
use App\Entity\User;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use App\Repository\PriceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VoucherController extends AbstractController
{
    public function __construct(
        private readonly PriceRepository $priceRepository,
        private readonly VoucherManager $voucherManager,
        private readonly InvoiceManager $invoiceManager,
        private readonly array $availablePaymentMethods
    ) {
    }

    #[Route('/voucher', name: 'voucher_index')]
    public function index(Request $request): Response
    {
        $response = new Response();

        if (false === $request->isMethod('POST')) {
            return $this->renderVoucherTemplate($response);
        }

        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('voucher', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response);
        }

        $voucherPriceId = $request->request->get('voucherPrice');
        if (null === $voucherPriceId) {
            $this->addFlash('error', 'Please select a voucher.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response);
        }

        $voucherPrice = $this->priceRepository->find($voucherPriceId);
        if (null === $voucherPrice) {
            $this->addFlash('error', 'Selected voucher not found in the database.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response);
        }

        $paymentMethod = $request->request->get('paymentMethod');
        if (null === $paymentMethod) {
            $this->addFlash('error', 'Please select a payment method.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response);
        }

        if (false === \in_array($paymentMethod, $this->availablePaymentMethods, true)) {
            $this->addFlash('error', 'Invalid payment method selected.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response);
        }
        $user = $this->getUser();
        if (false === $user instanceof User) {
            throw new \LogicException('User is not a User Object?!');
        }

        if ('invoice' === $paymentMethod) {
            $vouchers = $this->voucherManager->createVouchers($user, $voucherPrice->getVoucherType(), 0);
            $invoice  = $this->invoiceManager->createVoucherInvoice($user, $voucherPrice, $vouchers);

            $this->invoiceManager->generateVoucherInvoicePdf($invoice, $voucherPrice);
            $this->invoiceManager->sendVoucherInvoicePerEmail($invoice, $voucherPrice);

            // success messages
            return $this->redirectToRoute('user_vouchers');
        }

        return $this->redirectToRoute('voucher_payment_paypal', ['voucherPriceId' => $voucherPriceId]);
    }

    #[Route('/voucher/paypal/{voucherPriceId}', name: 'voucher_payment_paypal')]
    public function payVouchersPaypal(Price $price): Response
    {
        if (false === \in_array('paypal', $this->availablePaymentMethods, true)) {
            throw $this->createNotFoundException('Paypal is not available');
        }

        if (false === $price->isVoucher()) {
            throw $this->createNotFoundException('Price is not a voucher price');
        }

        return $this->render('voucher/paypal.html.twig', [
            'price' => $price,
        ]);
    }

    private function renderVoucherTemplate(Response $response): Response
    {
        $voucherPrices = $this->priceRepository->findActiveVoucherPrices();

        if (empty($voucherPrices)) {
            throw new \Exception('No voucher price found.');
        }

        return $this->render('voucher/index.html.twig', [
            'voucherPrices'  => $voucherPrices,
            'paymentMethods' => $this->availablePaymentMethods,
        ], $response);
    }
}
