<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingPaymentController extends AbstractController
{
    #[Route('/booking/{booking}/payment', name: 'booking_step_payment')]
    public function bookingStepThree(
        Request $request,
        Booking $booking,
    ): Response {
        if ($this->getUser() !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this booking');
        }

        return $this->render('booking/payment.html.twig', [
            'booking' => $booking,
        ]);
    }
}
