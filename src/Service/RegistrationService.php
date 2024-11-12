<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Manager\UserManager;
use App\Manager\UserTermsOfUseManager;
use App\Service\Security\EmailVerifier;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    use EmailContextTrait;

    public function __construct(
        private readonly UserManager $userManager,
        private readonly EmailVerifier $emailVerifier,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly UserMailerService $userMailer,
        private readonly UserTermsOfUseManager $userTermsOfUseManager,
    ) {
    }

    public function registerUser(User $user, string $plainPassword): void
    {
        if (null === $user->getPassword()) {
            $this->userManager->saveUserPassword($user, $plainPassword);
        }

        $this->saveCodeOfConductAcceptance($user);
        $this->saveDataProtectionAcceptance($user);
        $this->userTermsOfUseManager->saveAcceptanceTermsOfUse($user);
    }

    public function sendRegistrationEmail(User $user): void
    {
        $email = $user->getEmail();
        if (null === $email) {
            throw new \LogicException('User email is required to send registration email');
        }

        $subject         = $this->translator->trans('registration.email_verification.subject', [], 'email');
        $specificContext = $this->emailVerifier->getEmailConfirmationContext('app_verify_email', $user);
        $standardContext = [
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION => $this->translator->trans(
                    'registration.email_verification.salutation',
                    ['%firstName%' => $user->getFirstName()],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans('registration.email_verification.instructions', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION  => $this->translator->trans(
                    'registration.email_verification.explanation',
                    [
                        '%timeLimit%' => $this->translator->trans(
                            $specificContext['expiresAtMessageKey'],
                            $specificContext['expiresAtMessageData'],
                            'VerifyEmailBundle'
                        ),
                    ],
                    'email'
                ),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE   => $this->translator->trans('registration.email_verification.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT     => $subject,
                self::EMAIL_STANDARD_ELEMENT_BUTTON_TEXT => $this->translator->trans('registration.email_verification.button_text', [], 'email'),
            ],
            'link' => $specificContext['signedUrl'],
        ];

        $context = array_merge($specificContext, $standardContext);

        $this->userMailer->sendTemplatedEmail($email, $subject, $context, 'registration/email_confirmation.html.twig');
    }

    private function saveCodeOfConductAcceptance(User $user): void
    {
        if (null !== $user->getAcceptedCodeOfConduct()) {
            throw new \LogicException('User Registration is finished. User already accepted the code of conduct');
        }

        $user->setAcceptedCodeOfConduct(new \DateTime());

        if (null === $user->getId()) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
    }

    private function saveDataProtectionAcceptance(User $user): void
    {
        if (null !== $user->getAcceptedDataProtection()) {
            throw new \LogicException('User Registration is finished. User already accepted the data protection policy');
        }

        $user->setAcceptedDataProtection(new \DateTime());

        if (null === $user->getId()) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
    }
}
