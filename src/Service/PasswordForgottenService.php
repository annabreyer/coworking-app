<?php

declare(strict_types = 1);

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

        $timeLimit = $this->translator->trans($resetToken->getExpirationMessageKey(), $resetToken->getExpirationMessageData(), 'ResetPasswordBundle');

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail()))
            ->subject($this->translator->trans('email.reset-password.subject'))
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'texts'      => [
                    'salutation'   => $this->translator->trans('email.reset-password.salutation',
                        ['%firstName%' => $user->getFirstName()]),
                    'instructions' => $this->translator->trans('email.reset-password.instructions'),
                    'explanation'  => $this->translator->trans('email.reset-password.explanation', [
                        '%timeLimit%' => $timeLimit,
                    ]),
                    'signature'    => $this->translator->trans('email.reset-password.signature'),
                ],
            ])
        ;

        $this->mailer->send($email);
    }
}
