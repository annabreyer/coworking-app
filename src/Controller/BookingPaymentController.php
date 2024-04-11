<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Price;
use App\Manager\InvoiceManager;
use App\Repository\PriceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingPaymentController extends AbstractController
{
    #[Route('/booking/{booking}/payment', name: 'booking_step_payment')]
    public function bookingStepThree(
        Booking $booking,
    ): Response {
        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        return $this->render('booking/payment.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/booking/{booking}/payment/invoice', name: 'booking_payment_invoice')]
    public function laterPayment(
        Booking $booking,
        PriceRepository $priceRepository,
        InvoiceManager $invoiceManager
    ): Response {
        $singlePrice = $priceRepository->findOneBy([
            'type'     => Price::TYPE_SINGLE,
            'isActive' => true,
        ]);

        if (null === $singlePrice) {
            throw $this->createNotFoundException('No price found');
        }

        $invoice = $invoiceManager->createInvoiceFromBooking($booking, $singlePrice);
        $invoiceManager->generateInvoicePdf($invoice);
        $invoiceManager->sendInvoicePerEmail($invoice);

        return $this->render('booking/confirmation.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/booking/{booking}/payment/paypal', name: 'booking_payment_paypal')]
    public function payWithPayPal(): Response
    {
        return $this->render('booking/payment.html.twig');
    }

    #[Route('/booking/{booking}/payment/voucher', name: 'booking_payment_voucher')]
    public function payWithVoucher(): Response
    {
        return $this->render('booking/payment.html.twig');
    }
}
