<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Manager\UserManager;
use App\Manager\UserTermsOfUseManager;
use App\Service\Security\EmailVerifier;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    use EmailContextTrait;

    public function __construct(
        private UserManager $userManager,
        private EmailVerifier $emailVerifier,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private MailerInterface $mailer,
        private UserTermsOfUseManager $userTermsOfUseManager
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

        $specificContext = $this->emailVerifier->getEmailConfirmationContext('app_verify_email', $user);
        $standardContext = $this->getStandardEmailContext($this->translator, 'confirm-email');
        $context         = array_merge($specificContext, $standardContext);

        $context['texts']['salutation'] = $this->translator->trans(
            'registration.email_verification.email.salutation',
            ['%firstName%' => $user->getFirstName()]
        );
        $context['texts']['button']      = $this->translator->trans('registration.email_verification.email.button');
        $context['texts']['explanation'] = $this->translator->trans('registration.email_verification.email.explanation', [
            '%timeLimit%' => $this->translator->trans(
                $context['expiresAtMessageKey'],
                $context['expiresAtMessageData'],
                'VerifyEmailBundle'
            ),
        ]);

        $email = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject($this->translator->trans('registration.email_verification.email.subject'))
            ->context($context)
            ->htmlTemplate('registration/email_confirmation.html.twig')
        ;

        $this->mailer->send($email);
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
