<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Entity\Booking;
use App\Manager\BookingManager;
use App\Repository\PriceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingPaymentController extends AbstractController
{
    public function __construct(
        private readonly PriceRepository $priceRepository,
        private readonly BookingManager $bookingManager
    ) {
    }

    #[Route('/booking/{booking}/payment', name: 'booking_step_payment')]
    public function bookingStepThree(Booking $booking, Request $request): Response
    {
        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        $response = new Response();
        if (false === $request->isMethod('POST')) {
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

            return $this->redirectToRoute('booking_payment_invoice', ['booking' => $booking->getId()]);
        }

        if ('paypal' === $paymentMethod) {
            return $this->redirectToRoute('booking_payment_paypal', ['booking' => $booking->getId()]);

        }

        if ('voucher' === $paymentMethod) {
            return $this->redirectToRoute('booking_payment_voucher', ['booking' => $booking->getId()]);
        }

        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $this->addFlash('error', 'Payment method not found.');

        return $this->renderStepPayment($response, $booking);
    }

    #[Route('/booking/{booking}/invoice', name: 'booking_payment_invoice')]
    public function laterPayment(Booking $booking): Response
    {
        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        if (null === $booking->getInvoice()) {
            return $this->redirectToRoute('booking_step_payment', ['booking' => $booking->getId()]);
        }

        return $this->render('booking/confirmation.html.twig', [
            'invoice' => $booking->getInvoice(),
        ]);
    }

    #[Route('/booking/{booking}/payment/paypal', name: 'booking_payment_paypal')]
    public function payWithPayPal(Booking $booking): Response
    {
        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        return $this->renderStepPayment(new Response(), $booking);
    }

    #[Route('/booking/{booking}/payment/voucher', name: 'booking_payment_voucher')]
    public function payWithVoucher(Booking $booking): Response
    {
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
