<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Manager\UserTermsOfUseManager;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private UserPasswordHasherInterface $userPasswordHasher,
        private EntityManagerInterface $entityManager,
        private UserTermsOfUseManager $userTermsOfUseManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function saveUserFromRegistrationForm(User $user, string $plainPassword): void
    {
        // encode the plain password
        $user->setPassword(
            $this->userPasswordHasher->hashPassword(
                $user,
                $plainPassword
            )
        );

        $now = new \DateTimeImmutable('now');
        $user->setAcceptedDataProtection($now);
        $user->setAcceptedCodeOfConduct($now);

        $this->entityManager->persist($user);

        $this->entityManager->flush();

        $this->userTermsOfUseManager->saveAcceptanceTermsOfUse($user);
    }

    public function sendRegistrationEmail(User $user): void
    {
        $email = $user->getEmail();
        if (null === $email) {
            throw new \LogicException('User email is required to send registration email');
        }

        $emailAddress = new Address($email);
        $subject      = $this->translator->trans('Please Confirm your Email');
        // generate a signed url and email it to the user
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->to($emailAddress)
                ->subject($subject)
                ->htmlTemplate('registration/confirmation_email.html.twig')
        );

        // do anything else you need here, like send an email
    }
}
