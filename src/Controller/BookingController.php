<?php declare(strict_types = 1);

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\User;
use App\Manager\BookingManager;
use App\Repository\BusinessDayRepository;
use App\Repository\RoomRepository;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    public function __construct(
        private BookingService $bookingService,
        private BusinessDayRepository $businessDayRepository,
        private RoomRepository $roomRepository,
        private ClockInterface $clock
    ) {
    }

    #[Route('/booking', name: 'booking_step_date', methods: ['GET', 'POST'])]
    public function bookingStepDate(Request $request): Response
    {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepDate(new Response(), $this->clock->now());
        }

        $response       = new Response();
        $submittedToken = $request->getPayload()->get('token');

        if (false === $this->isCsrfTokenValid('date', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, $this->clock->now());
        }

        $date = $request->request->get('date');
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
        BookingManager $bookingManager
    ): Response {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepRoom(new Response(), $businessDay);
        }

        $response = new Response();
        $user     = $this->getUser();
        if (false === $user instanceof User) {
            throw new \Exception('User is not an instance of User ?!');
        }

        $submittedToken = $request->getPayload()->get('token');

        if (false === $this->isCsrfTokenValid('room', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        $roomId = $request->request->get('room');
        if (empty($roomId) || false === is_numeric($roomId)) {
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

        $booking = $bookingManager->saveBooking($user, $businessDay, $room);
        //@todo send email for booking

        return $this->redirectToRoute('booking_step_payment', ['booking' => $booking->getId()]);
    }

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

    private function renderStepDate(Response $response, \DateTimeImmutable $dateTime): Response
    {
        $businessDays = $this->businessDayRepository->findBusinessDaysAfterDate($this->clock->now());

        return $this->render('booking/index.html.twig', [
            'step'    => 1,
            'lastDay' => $businessDays[count($businessDays) - 1]->getDate()->format('Y-m-d'),
            'date'    => $dateTime->format('Y-m-d'),
        ], $response);
    }

    private function renderStepRoom(Response $response, BusinessDay $businessDay): Response
    {
        $businessDays  = $this->businessDayRepository->findBusinessDaysAfterDate($this->clock->now());
        $bookingOption = $this->bookingService->generateAvailableBookingOptionsForDay($businessDay, false);

        return $this->render('booking/index.html.twig', [
            'step'          => 2,
            'lastDay'       => $businessDays[count($businessDays) - 1]->getDate()->format('Y-m-d'),
            'bookingOption' => $bookingOption,
            'date'          => $businessDay->getDate()->format('Y-m-d'),
            'businessDay'   => $businessDay,
        ], $response);
    }
}