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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    public function __construct(
        private BookingService $bookingService,
        private BusinessDayRepository $businessDayRepository,
        private RoomRepository $roomRepository
    ) {
    }

    #[Route('/booking', name: 'booking_step_date', methods: ['GET', 'POST'])]
    public function bookingStepDate(Request $request): Response
    {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepDate(new Response(), new \DateTimeImmutable());
        }

        $response       = new Response();
        $submittedToken = $request->getPayload()->get('token');

        if (false === $this->isCsrfTokenValid('date', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, new \DateTimeImmutable());
        }

        $date = $request->request->get('date');
        if (empty($date)) {
            $this->addFlash('error', 'No date selected');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, new \DateTimeImmutable());
        }

        try {
            $dateTime    = new \DateTimeImmutable($date);
        } catch (\Exception $exception) {
            $this->addFlash('error', 'Invalid date format');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, new \DateTimeImmutable());
        }

        $businessDay = $this->businessDayRepository->findOneBy(['date' => $dateTime]);

        if (null === $businessDay) {
            $this->addFlash('error', 'Requested Date is not a business day');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepDate($response, new \DateTimeImmutable());
        }

        return $this->redirectToRoute('booking_step_room', ['businessDay' => $businessDay->getId()]);
    }

    #[Route('/booking/{businessDay}/room', name: 'booking_step_room', methods: ['GET', 'POST'])]
    public function bookingStepRoom(Request $request, BusinessDay $businessDay, BookingManager $bookingManager): Response
    {
        if (false === $request->isMethod('POST')) {
            return $this->renderStepRoom(new Response(), $businessDay);
        }

        $response = new Response();
        $user     = $this->getUser();
        if (false === $user instanceof User) {
            throw new \Exception('You must be logged in make a booking');
        }

        $submittedToken = $request->getPayload()->get('token');

        if (false === $this->isCsrfTokenValid('room', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF Token');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderStepRoom($response, $businessDay);
        }

        $roomId = $request->request->getInt('room');
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

        $booking = $bookingManager->saveBooking($user, $businessDay, $room);

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
        $businessDays = $this->businessDayRepository->findBusinessDaysAfterDate(new \DateTimeImmutable());

        return $this->render('booking/index.html.twig', [
            'step'     => 1,
            'firstDay' => $businessDays[0]->getDate()->format('Y-m-d'),
            'lastDay'  => $businessDays[count($businessDays) - 1]->getDate()->format('Y-m-d'),
            'date'     => $dateTime->format('Y-m-d'),
        ], $response);
    }

    private function renderStepRoom(Response $response, BusinessDay $businessDay): Response
    {
        $businessDays  = $this->businessDayRepository->findBusinessDaysAfterDate(new \DateTimeImmutable());
        $bookingOption = $this->bookingService->generateAvailableBookingOptionsForDay($businessDay, false);

        return $this->render('booking/index.html.twig', [
            'step'          => 2,
            'firstDay'      => $businessDays[0]->getDate()->format('Y-m-d'),
            'lastDay'       => $businessDays[count($businessDays) - 1]->getDate()->format('Y-m-d'),
            'bookingOption' => $bookingOption,
            'date'          => $businessDay->getDate()->format('Y-m-d'),
            'businessDay'   => $businessDay,
        ], $response);
    }
}