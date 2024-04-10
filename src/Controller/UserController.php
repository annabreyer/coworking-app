<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\UserDataFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    use ClockAwareTrait;

    #[Route('/user', name: 'user_show')]
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

            return $this->redirectToRoute('user_show');
        }

        return $this->render('user/edit_user.html.twig', [
            'userForm' => $form->createView(),
        ]);
    }

    #[Route('/user/bookings', name: 'user_bookings')]
    public function showUserBookings(): Response
    {
        $timeLimitCancelBooking = $this->getParameter('time_limit_cancel_booking_days');

        if (false === \is_string($timeLimitCancelBooking)) {
            throw new \InvalidArgumentException('Parameter "time_limit_cancel_booking_days" must be a string.');
        }

        $limit = $this->now()->modify('-' . $timeLimitCancelBooking);
        $user  = $this->getUser();

        return $this->render('user/bookings.html.twig', [
            'user'  => $user,
            'now'   => $this->now(),
            'limit' => $limit,
        ]);
    }
}
