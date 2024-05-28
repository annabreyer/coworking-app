<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use App\Repository\PriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VoucherController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/voucher', name: 'voucher_index')]
    public function index(Request $request, PriceRepository $priceRepository, VoucherManager $voucherManager,InvoiceManager $invoiceManager,): Response
    {
        $voucherPrices = $priceRepository->findActiveVoucherPrices();

        if (empty($voucherPrices)) {
            $this->addFlash('error', 'Vouchers are currently not available.');
            $this->logger->error('No voucher prices available.');

            return $this->redirectToRoute('home');
        }

        $response = new Response();

        if (false === $request->isMethod('POST')) {
            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('voucher', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        $voucherPriceId = $request->request->get('voucherPrice');
        if (null === $voucherPriceId) {
            $this->addFlash('error', 'Please select a voucherPrice.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        $voucherPrice = $priceRepository->find($voucherPriceId);
        if (null === $voucherPrice) {
            $this->addFlash('error', 'Selected voucherPrice is not available.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        $paymentMethod = $request->request->get('paymentMethod');
        if (null === $paymentMethod) {
            $this->addFlash('error', 'Please select a payment method.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        if (false === \in_array($paymentMethod, ['invoice', 'paypal'], true)) {
            $this->addFlash('error', 'Invalid payment method selected.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        /** @var User $user */
        $user    = $this->getUser();

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $invoice = $invoiceManager->createVoucherInvoice($user, $voucherPrice);
            $voucherManager->createVouchers($user, $voucherPrice->getVoucherType(), 0, $invoice);

            $this->entityManager->getConnection()->commit();
        } catch (\Exception $exception) {
            $this->entityManager->getConnection()->rollback();
            $this->logger->error('Vouchers and Invoice were not created for User '. $user->getId() .' : ' . $exception->getMessage());

            $this->addFlash('error', 'An error occurred. Please try again later.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherTemplate($response, $voucherPrices);
        }

        if ('invoice' === $paymentMethod) {
            $invoiceManager->generateVoucherInvoicePdf($invoice);
            $invoiceManager->sendVoucherInvoiceToUser($invoice);
            $invoiceManager->sendInvoiceToDocumentVault($invoice);

            // @todo success messages
            return $this->redirectToRoute('user_vouchers');
        }

        return $this->redirectToRoute('invoice_payment_paypal', ['uuid' => $invoice->getUuid()]);
    }

    private function renderVoucherTemplate(Response $response, array $voucherPrices): Response
    {
        return $this->render('voucher/index.html.twig', [
            'voucherPrices'  => $voucherPrices,
            'paymentMethods' => ['invoice', 'paypal'],
        ], $response);
    }
}
