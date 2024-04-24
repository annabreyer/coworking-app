<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BusinessDay;
use App\Entity\User;
use App\Manager\BookingManager;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\PriceRepository;
use App\Repository\RoomRepository;
use App\Service\AdminMailerService;
use App\Service\BookingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BookingService $bookingService,
        private readonly BookingManager $bookingManager,
        private readonly BookingRepository $bookingRepository,
        private readonly BusinessDayRepository $businessDayRepository,
        private readonly RoomRepository $roomRepository,
        private readonly ClockInterface $clock,
        private readonly PriceRepository $priceRepository,
        private readonly string $timeLimitCancelBooking
    ) {
    }

    #[Route('/booking', name: 'booking_step_date', methods: ['GET', 'POST'])]
    public function bookingStepDate(Request $request): Response
    {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepDate(new Response(), $this->clock->now());
        }

        $response       = new Response();
        $submittedToken = $request->getPayload()->getString('token_date');

        if (false === $this->isCsrfTokenValid('date', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        $date = $request->request->getString('date');
        if (empty($date)) {
            $this->addFlash('error', 'No date selected');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        try {
            $dateTime = new \DateTimeImmutable($date);
        } catch (\DateMalformedStringException $exception) {
            $dateTime = false;
        }

        if (false === $dateTime) {
            $this->addFlash('error', 'Invalid date format.');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        if ($dateTime < $this->clock->now()) {
            $this->addFlash('error', 'Date must be in the future');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        $businessDay = $this->businessDayRepository->findOneBy(['date' => $dateTime]);

        if (null === $businessDay || false === $businessDay->isOpen()) {
            $this->addFlash('error', 'Requested Date is not a business day');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        return $this->redirectToRoute('booking_step_room', ['businessDay' => $businessDay->getId()]);
    }

    #[Route('/booking/{businessDay}/room', name: 'booking_step_room', methods: ['GET', 'POST'])]
    public function bookingStepRoom(
        Request $request,
        BusinessDay $businessDay,
        AdminMailerService $adminMailerService
    ): Response {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepRoom(new Response(), $businessDay);
        }

        $response = new Response();
        $user     = $this->getUser();
        if (false === $user instanceof User) {
            throw new \Exception('User is not an instance of User ?!');
        }

        $submittedToken = $request->getPayload()->getString('token');

        if (false === $this->isCsrfTokenValid('room', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        $roomId = $request->request->get('room');
        if (empty($roomId)) {
            $this->addFlash('error', 'No room selected');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        $room = $this->roomRepository->find($roomId);
        if (null === $room) {
            $this->addFlash('error', 'Unknown room selected');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        if ($room->getCapacity() < 1) {
            $this->addFlash('error', 'Room is already fully booked');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        if ($room->getCapacity() <= $businessDay->getBookingsForRoom($room)->count()) {
            $this->addFlash('error', 'Room is already fully booked');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        $booking = $this->bookingManager->saveBooking($user, $businessDay, $room);
        $adminMailerService->notifyAdminAboutBooking($booking);

        return $this->redirectToRoute('booking_step_payment', ['uuid' => $booking->getUuid()]);
    }

    #[Route('/booking/{uuid}/cancel', name: 'booking_cancel', methods: ['POST'])]
    public function cancelBooking(string $uuid, Request $request, AdminMailerService $adminMailerService): Response
    {
        try {
            $booking = $this->bookingRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $booking = null;
        }
        if (null === $booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        $user = $this->getUser();
        if ($user !== $booking->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to cancel this booking.');
        }

        $bookingId = $request->request->getInt('bookingId');
        if ($bookingId !== $booking->getId()) {
            $this->addFlash('error', 'Booking can not be cancelled.');

            return $this->redirectToRoute('user_bookings');
        }

        if (false === $this->bookingManager->canBookingBeCancelled($booking)) {
            $this->addFlash(
                'error',
                sprintf('Bookings can only be cancelled %s before their date.', $this->timeLimitCancelBooking)
            );

            return $this->redirectToRoute('user_bookings');
        }

        if (null === $booking->getBusinessDay() || null === $booking->getBusinessDay()->getDate()) {
            throw new \Exception('Persisted Booking has no BusinessDay ?!');
        }

        $bookingDate = $booking->getBusinessDay()->getDate();
        $this->bookingManager->cancelBooking($booking);

        $adminMailerService->notifyAdminAboutBookingCancellation($bookingDate);

        $this->addFlash('success', sprintf('Booking for date %s has been cancelled', $bookingDate->format('Y-m-d')));

        return $this->redirectToRoute('user_bookings');
    }

    private function renderStepDate(Response $response, \DateTimeImmutable $dateTime): Response
    {
        $businessDays     = $this->businessDayRepository->findBusinessDaysStartingWithDate($this->clock->now());
        $businessDayCount = \count($businessDays);

        if (0 === $businessDayCount) {
            $this->logger->critical('No BusinessDays found.');
            $this->addFlash('error', 'We are sorry for the inconvenience. Please try again later.');

            return $this->redirectToRoute('home');
        }

        $activePrices = $this->priceRepository->findActivePrices();

        if (empty($activePrices)) {
            $this->logger->critical('No active Price found.');
            $this->addFlash('error', 'We are sorry for the inconvenience. Please try again later.');

            return $this->redirectToRoute('home');
        }

        return $this->render('booking/index.html.twig', [
            'step'     => 1,
            'firstDay' => $businessDays[0]->getDate()->format('Y-m-d'),
            'lastDay'  => $businessDays[$businessDayCount - 1]->getDate()->format('Y-m-d'),
            'date'     => $dateTime->format('Y-m-d'),
            'prices'   => $activePrices,
        ], $response);
    }

    private function renderStepRoom(Response $response, BusinessDay $businessDay): Response
    {
        $businessDays     = $this->businessDayRepository->findBusinessDaysStartingWithDate($this->clock->now());
        $businessDayCount = \count($businessDays);

        if (0 === $businessDayCount) {
            throw new \Exception('No BusinessDays found ?!');
        }

        if (false === isset($businessDays[$businessDayCount - 1])) {
            throw new \Exception('No Last BusinessDay found ?!');
        }

        if (null === $businessDays[0]->getDate()) {
            throw new \Exception('First BusinessDay has no Date ?!');
        }

        if (null === $businessDays[$businessDayCount - 1]->getDate()) {
            throw new \Exception('Last BusinessDay has no Date ?!');
        }

        if (null === $businessDay->getDate()) {
            throw new \Exception('BusinessDay has no Date ?!');
        }

        $activePrices = $this->priceRepository->findActivePrices();

        if (empty($activePrices)) {
            throw new \Exception('No active Price found ?!');
        }

        $bookingOption = $this->bookingService->generateAvailableBookingOptionsForDay($businessDay, false);

        return $this->render('booking/index.html.twig', [
            'step'          => 2,
            'firstDay'      => $businessDays[0]->getDate()->format('Y-m-d'),
            'lastDay'       => $businessDays[$businessDayCount - 1]->getDate()->format('Y-m-d'),
            'bookingOption' => $bookingOption,
            'date'          => $businessDay->getDate()->format('Y-m-d'),
            'businessDay'   => $businessDay,
            'prices'        => $activePrices,
        ], $response);
    }
}
