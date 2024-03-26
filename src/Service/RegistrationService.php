<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Manager\UserTermsOfUseManager;
use App\Security\EmailVerifier;
use App\Trait\EmailContextTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    use EmailContextTrait;

    public function __construct(
        private EmailVerifier $emailVerifier,
        private UserPasswordHasherInterface $userPasswordHasher,
        private EntityManagerInterface $entityManager,
        private UserTermsOfUseManager $userTermsOfUseManager,
        private TranslatorInterface $translator,
        private MailerInterface $mailer
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

        $specificContext = $this->emailVerifier->getEmailConfirmationContext('app_verify_email', $user);
        $standardContext = $this->getStandardEmailContext($this->translator, 'confirm-email');
        $context         = array_merge($specificContext, $standardContext);

        $context['texts']['salutation'] = $this->translator->trans(
            'email.confirm-email.salutation',
            ['%firstName%' => $user->getFirstName()]
        );
        $context['texts']['button']      = $this->translator->trans('email.confirm-email.button');
        $context['texts']['explanation'] = $this->translator->trans('email.confirm-email.explanation', [
            '%timeLimit%' => $this->translator->trans(
                $context['expiresAtMessageKey'],
                $context['expiresAtMessageData'],
                'VerifyEmailBundle'
            ),
        ]);

        $email = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject($this->translator->trans('email.confirm-email.subject'))
            ->context($context)
            ->htmlTemplate('registration/email_confirmation.html.twig')
        ;

        $this->mailer->send($email);
    }
}
