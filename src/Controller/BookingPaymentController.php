<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Entity\Booking;
use App\Manager\BookingManager;
use App\Repository\BookingRepository;
use App\Repository\PriceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingPaymentController extends AbstractController
{
    public function __construct(
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
            $booking = null;
        }
        if (null === $booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        $response = new Response();
        if (false === $request->isMethod('POST')) {
            return $this->renderStepPayment($response, $booking);
        }

        $submittedToken = $request->getPayload()->getString('token');
        if (false === $this->isCsrfTokenValid('payment', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
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

        if ('invoice' === $paymentMethod) {
            $this->bookingManager->handleBookingPaymentByInvoice($booking, $price);

            return $this->redirectToRoute('booking_payment_invoice', ['uuid' => $booking->getUuid()]);
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

    #[Route('/booking/{uuid}/invoice', name: 'booking_payment_invoice')]
    public function laterPayment(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $booking = null;
        }
        if (null === $booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        if (null === $booking->getInvoice()) {
            return $this->redirectToRoute('booking_step_payment', ['uuid' => $booking->getUuid()]);
        }

        return $this->render('booking/confirmation.html.twig', [
            'invoice' => $booking->getInvoice(),
        ]);
    }

    #[Route('/booking/{uuid}/payment/paypal', name: 'booking_payment_paypal')]
    public function payWithPayPal(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $booking = null;
        }
        if (null === $booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        return $this->renderStepPayment(new Response(), $booking);
    }

    #[Route('/booking/{uudi}/payment/voucher', name: 'booking_payment_voucher')]
    public function payWithVoucher(string $uuid): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $booking = null;
        }
        if (null === $booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        return $this->renderStepPayment(new Response(), $booking);
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
}
