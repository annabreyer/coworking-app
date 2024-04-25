<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Manager\BookingManager;
use App\Repository\BookingRepository;
use App\Repository\PriceRepository;
use App\Repository\VoucherRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingPaymentController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PriceRepository $priceRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly BookingManager $bookingManager
    ) {
    }

    #[Route('/booking/{uuid}/payment', name: 'booking_step_payment')]
    public function bookingStepThree(string $uuid, Request $request): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. '. $exception->getMessage(), ['uuid' => $uuid]);
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
            $this->addFlash('error', 'Invalid CSRF Token.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepPayment($response, $booking);
        }

        $priceId = $request->request->get('priceId');
        if (empty($priceId)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'PriceId is missing.');

            return $this->renderStepPayment($response, $booking);
        }

        $price = $this->priceRepository->find($priceId);
        if (null === $price) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Price not found.');

            return $this->renderStepPayment($response, $booking);
        }

        $paymentMethod = $request->request->get('paymentMethod');
        if (empty($paymentMethod)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Payment method is missing.');

            return $this->renderStepPayment($response, $booking);
        }

        $this->bookingManager->addAmountToBooking($booking, $price);

        if ('invoice' === $paymentMethod) {
            $this->bookingManager->handleBookingPaymentByInvoice($booking);

            return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
        }

        if ('paypal' === $paymentMethod) {
            return $this->redirectToRoute('booking_payment_paypal', ['uuid' => $booking->getUuid()]);
        }

        if ('voucher' === $paymentMethod) {
            return $this->redirectToRoute('booking_payment_voucher', ['uuid' => $booking->getUuid()]);
        }

        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $this->addFlash('error', 'Payment method not found.');

        return $this->renderStepPayment($response, $booking);
    }

    #[Route('/booking/{uuid}/payment/paypal', name: 'booking_payment_paypal')]
    public function payWithPayPal(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. '. $exception->getMessage(), ['uuid' => $uuid]);
            $booking = null;
        }
        if (null === $booking) {
            $this->redirectToRoute('booking_step_date');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking.');
        }

        return $this->renderStepPayment(new Response(), $booking);
    }

    #[Route('/booking/{uuid}/payment/voucher', name: 'booking_payment_voucher')]
    public function payWithVoucher(string $uuid, Request $request, VoucherRepository $voucherRepository): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. '. $exception->getMessage(), ['uuid' => $uuid]);
            $booking = null;
        }
        if (null === $booking) {
            return $this->redirectToRoute('booking_step_date');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking.');
        }

        if ($booking->hasBeenPaid()) {
            return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
        }

        if (false === $request->isMethod('POST')) {
            return $this->renderVoucherPayment(new Response(), $booking);
        }

        $response       = new Response();
        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('voucher', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderVoucherPayment($response, $booking);
        }

        $voucherCode = $request->getPayload()->getString('voucher');
        if (empty($voucherCode)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher code is missing.');

            return $this->renderVoucherPayment($response, $booking);
        }

        $voucher = $voucherRepository->findOneBy(['code' => $voucherCode]);
        if (null === $voucher) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher not found.');

            return $this->renderVoucherPayment($response, $booking);
        }

        if ($voucher->getUser() !== $booking->getUser()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher is not valid for this user.');

            return $this->renderVoucherPayment($response, $booking);
        }

        if ($voucher->isExpired()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher is expired.');

            return $this->renderVoucherPayment($response, $booking);
        }

        if (null !== $voucher->getUseDate()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher has already been used on ' . $voucher->getUseDate()->format('Y-m-d') . '.');

            return $this->renderVoucherPayment($response, $booking);
        }

        if (false === $voucher->hasBeenPaid()) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', 'Voucher has not been paid and cannot be used.');

            return $this->renderVoucherPayment($response, $booking);
        }

        $this->bookingManager->handleBookingPaymentByVoucher($booking, $voucher);

        return $this->redirectToRoute('booking_payment_confirmation', ['uuid' => $booking->getUuid()]);
    }

    #[Route('/booking/{uuid}/payment/confirmation', name: 'booking_payment_confirmation')]
    public function bookingPaymentConfirmation(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Booking not found. '. $exception->getMessage(), ['uuid' => $uuid]);
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
