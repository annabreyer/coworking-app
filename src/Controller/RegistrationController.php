<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use App\Service\Security\EmailVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EmailVerifier $emailVerifier
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, RegistrationService $registrationService, Security $security): ?Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $registrationService->registerUser($user, $plainPassword);
            $registrationService->sendRegistrationEmail($user);

            $this->addFlash('success', $this->translator->trans('form.registration.success', [], 'flash'));

            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            $this->addFlash('error', $this->translator->trans('form.registration.email_verification.error.generic', [], 'flash'));
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            $this->addFlash('error', $this->translator->trans('form.registration.email_verification.error.generic', [], 'flash'));
            return $this->redirectToRoute('app_register');
        }

        $loggedInUser = $this->getUser();

        if (null !==  $loggedInUser && $user->getId() !== $loggedInUser->getId()) {
            $this->addFlash('error', $this->translator->trans('form.registration.email_verification.error.not_allowed', [], 'flash'));
            return $this->redirectToRoute('app_logout');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $this->translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', $this->translator->trans('form.registration.email_verification.success', [], 'flash'));

        return $this->redirectToRoute('app_login');
    }
}
