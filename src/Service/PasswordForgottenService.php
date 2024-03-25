<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class PasswordForgottenService
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendPasswordForgottenEmail(User $user, ResetPasswordToken $resetToken): void
    {
        if (null === $user->getEmail()) {
            throw new \LogicException('User must have an email');
        }

        $emailAddress = new Address($user->getEmail());
        $subject      = $this->translator->trans('Your password reset request');
        $email        = (new TemplatedEmail())
            ->to($emailAddress)
            ->subject($subject)
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $this->mailer->send($email);
    }
}
