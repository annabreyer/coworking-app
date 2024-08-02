<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use App\Manager\PaymentManager;
use App\Repository\BookingRepository;
use App\Repository\PriceRepository;
use App\Repository\VoucherRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingPaymentController extends AbstractController
{
    public const BOOKING_STEP_PAYMENT = 'booking_step_payment';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly PriceRepository $priceRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly BookingManager $bookingManager,
        private readonly PaymentManager $paymentManager,
        private readonly InvoiceManager $invoiceManager,
    ) {
    }

    #[Route('/booking/{uuid}/payment', name: 'booking_step_payment')]
    public function bookingStepThree(string $uuid, Request $request): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $booking = null;
        }
        if (null === $booking) {
            return $this->redirectToRoute('booking_step_date');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking.');
        }

        $response = new Response();
        if (false === $request->isMethod('POST')) {
            return $this->renderStepPayment($response, $booking);
        }

        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('payment', $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('form.general.csrf_token_invalid', [], 'flash'));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepPayment($response, $booking);
        }

        $priceId = $request->request->get('priceId');
        if (empty($priceId)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.no_price', [], 'flash'));

            return $this->renderStepPayment($response, $booking);
        }

        $price       = $this->priceRepository->find($priceId);
        $priceAmount = $price?->getAmount();
        if (null === $price || null === $priceAmount) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.price_not_found', [], 'flash'));

            return $this->renderStepPayment($response, $booking);
        }

        $paymentMethod = $request->request->get('paymentMethod');
        if (empty($paymentMethod)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.no_payment_method', [], 'flash'));

            return $this->renderStepPayment($response, $booking);
        }

        $bookingAmount = $booking->getAmount();
        if (null === $bookingAmount) {
            $this->bookingManager->addAmountToBooking($booking, $priceAmount);
            $bookingAmount = $priceAmount;
        }

        $bookingInvoice = $booking->getInvoice();
        if (null === $bookingInvoice) {
            $bookingInvoice = $this->invoiceManager->createInvoiceFromBooking($booking, $bookingAmount);
        }

        if ('invoice' === $paymentMethod) {
            $this->invoiceManager->generateBookingInvoicePdf($bookingInvoice);
            $this->invoiceManager->sendBookingInvoiceToUser($bookingInvoice);
            $this->invoiceManager->sendInvoiceToDocumentVault($bookingInvoice);

            return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
        }

        if ('paypal' === $paymentMethod) {
            $request->getSession()->set(self::BOOKING_STEP_PAYMENT, true);

            return $this->redirectToRoute('invoice_payment_paypal', ['uuid' => $bookingInvoice->getUuid()]);
        }

        if ('voucher' === $paymentMethod) {
            return $this->redirectToRoute('booking_payment_voucher', ['uuid' => $booking->getUuid()]);
        }

        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $this->addFlash('error', $this->translator->trans('form.booking.payment.payment_method_not_found', [], 'flash'));

        return $this->renderStepPayment($response, $booking);
    }

    #[Route('/booking/{uuid}/payment/voucher', name: 'booking_payment_voucher')]
    public function payWithVoucher(string $uuid, Request $request, VoucherRepository $voucherRepository): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $booking = null;
        }
        if (null === $booking) {
            return $this->redirectToRoute('booking_step_date');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking.');
        }

        if ($booking->isFullyPaid()) {
            return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
        }

        if (null === $booking->getAmount()) {
            return $this->redirectToRoute('booking_step_payment', ['uuid' => $booking->getUuid()]);
        }

        if (false === $request->isMethod('POST')) {
            return $this->renderVoucherPayment(new Response(), $booking);
        }

        $response       = new Response();
        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('voucher', $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('form.general.csrf_token_invalid', [], 'flash'));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherPayment($response, $booking);
        }

        $voucherCode = $request->getPayload()->getString('voucher');
        if (empty($voucherCode)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.no_voucher_code', [], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        $voucher = $voucherRepository->findOneBy(['code' => $voucherCode]);
        if (null === $voucher) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.invalid_code', [], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        if ($voucher->getUser() !== $booking->getUser()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.not_for_user', [], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        if ($voucher->isExpired()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.voucher_expired', [], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        if (null !== $voucher->getUseDate()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.already_used', ['%date%' => $voucher->getUseDate()->format('Y-m-d')], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        if (false === $voucher->isFullyPaid()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $this->translator->trans('form.booking.payment.voucher.not_paid', [], 'flash'));

            return $this->renderVoucherPayment($response, $booking);
        }

        if (null === $booking->getInvoice()) {
            return $this->redirectToRoute('booking_step_payment', ['uuid' => $booking->getUuid()]);
        }

        $invoice = $this->paymentManager->handleVoucherPayment($booking->getInvoice(), $voucher);
        $this->invoiceManager->generateBookingInvoicePdf($invoice);
        $this->invoiceManager->sendBookingInvoiceToUser($invoice);

        return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
    }

    #[Route('/booking/{uuid}/payment/confirmation', name: 'booking_payment_confirmation')]
    public function bookingPaymentConfirmation(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. ' . $exception->getMessage(), ['uuid' => $uuid]);
            $booking = null;
        }
        if (null === $booking) {
            return $this->redirectToRoute('booking_step_date');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking.');
        }

        if (null === $booking->getInvoice()) {
            return $this->redirectToRoute('booking_step_payment', ['uuid' => $booking->getUuid()]);
        }

        return $this->render('booking/confirmation.html.twig', [
            'invoice' => $booking->getInvoice(),
        ]);
    }

    private function renderStepPayment(Response $response, Booking $booking): Response
    {
        $unitaryPrice = $this->priceRepository->findOneBy([
            'isUnitary' => true,
            'isActive'  => true,
        ]);

        return $this->render('booking/payment.html.twig', [
            'booking'      => $booking,
            'unitaryPrice' => $unitaryPrice,
        ], $response);
    }

    private function renderVoucherPayment(Response $response, Booking $booking): Response
    {
        return $this->render('booking/voucher_payment.html.twig', [
            'booking' => $booking,
        ], $response);
    }
}
