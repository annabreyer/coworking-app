<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Trait\EmailContextTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class PasswordForgottenService
{
    use EmailContextTrait;

    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendPasswordForgottenEmail(User $user, ResetPasswordToken $resetToken): void
    {
        if (null === $user->getEmail()) {
            throw new \LogicException('User email is required to send password forgotten email');
        }

        $timeLimit = $this->translator->trans(
            $resetToken->getExpirationMessageKey(),
            $resetToken->getExpirationMessageData(),
            'ResetPasswordBundle'
        );
        $context                        = $this->getStandardEmailContext($this->translator, 'reset-password');
        $context['resetToken']          = $resetToken;
        $context['texts']['salutation'] = $this->translator->trans(
            'reset_password.email.salutation',
            ['%firstName%' => $user->getFirstName()]
        );
        $context['texts']['explanation'] = $this->translator->trans(
            'reset_password.email.explanation',
            ['%timeLimit%' => $timeLimit]
        );

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail()))
            ->subject($this->translator->trans('reset_password.email.subject'))
            ->htmlTemplate('reset_password/email.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
