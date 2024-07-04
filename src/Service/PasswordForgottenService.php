<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Trait\EmailContextTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class PasswordForgottenService
{
    use EmailContextTrait;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
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

        $subject = $this->translator->trans('reset_password.subject', [], 'email');
        $link    = $this->urlGenerator->generate('app_reset_password', ['token' => $resetToken->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $context = [
            'link'  => $link,
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $this->translator->trans('reset_password.salutation', ['%firstName%' => $user->getFirstName()], 'email'),
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans('reset_password.instructions', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION  => $this->translator->trans('reset_password.explanation', ['%timeLimit%' => $timeLimit], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE    => $this->translator->trans('reset_password.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT      => $subject,
                self::EMAIL_STANDARD_ELEMENT_BUTTON_TEXT  => $this->translator->trans('reset_password.button_text', [], 'email'),
            ],
        ];

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail()))
            ->subject($subject)
            ->htmlTemplate('email.base.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
