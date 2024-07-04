<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserDataFormType;
use App\Repository\BookingRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    use ClockAwareTrait;

    #[Route('/user', name: 'user_dashboard')]
    public function showUser(): Response
    {
        $user  = $this->getUser();
        $email = $this->getParameter('support_email');

        return $this->render('user/account.html.twig', [
            'user'         => $user,
            'supportEmail' => $email,
        ]);
    }

    #[Route('/user/edit', name: 'user_edit')]
    public function editUser(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserDataFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('user/edit_user.html.twig', [
            'userForm' => $form->createView(),
        ]);
    }

    #[Route('/user/bookings', name: 'user_bookings')]
    public function showUserBookings(BookingRepository $bookingRepository): Response
    {
        /** @var User $user */
        $user                   = $this->getUser();
        $timeLimitCancelBooking = $this->getParameter('time_limit_cancel_booking_days');

        if (false === \is_string($timeLimitCancelBooking)) {
            throw new \InvalidArgumentException('Parameter "time_limit_cancel_booking_days" must be a string.');
        }

        $limit            = $this->now()->modify('-' . $timeLimitCancelBooking);
        $bookings         = $bookingRepository->findBookingsForUserAfterDate($user, $this->now());
        $thisYearBookings = $bookingRepository->findBookingsForUserAndYear($user, $this->now()->format('Y'));

        return $this->render('user/bookings.html.twig', [
            'user'             => $user,
            'futureBookings'   => $bookings,
            'thisYearBookings' => $thisYearBookings,
            'now'              => $this->now(),
            'limit'            => $limit,
        ]);
    }

    #[Route('/user/vouchers', name: 'user_vouchers')]
    public function showUserVouchers(InvoiceRepository $invoiceRepository): Response
    {
        /** @var User $user */
        $user                   = $this->getUser();
        $pendingPaymentVouchers = $user->getPendingPaymentVouchers();

        return $this->render('user/vouchers.html.twig', [
            'user'                   => $user,
            'expiredOrUsedVouchers'  => $user->getExpiredOrUsedVouchers(),
            'validVouchers'          => $user->getValidVouchers(),
            'pendingPaymentVouchers' => $pendingPaymentVouchers,
        ]);
    }
}
