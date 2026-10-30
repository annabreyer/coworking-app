<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingCalendarController extends AbstractController
{
    public function __construct(
        private readonly string $bookingCalendarToken,
    ) {
    }

    #[Route('/booking-calendar/{token}.ics', name: 'app_booking_calendar', methods: ['GET'])]
    public function feed(string $token, BookingCalendarService $bookingCalendarService): Response
    {
        if ('' === $this->bookingCalendarToken || false === hash_equals($this->bookingCalendarToken, $token)) {
            throw $this->createNotFoundException();
        }

        return new Response($bookingCalendarService->generateCalendar(), Response::HTTP_OK, [
            'Content-Type'  => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
