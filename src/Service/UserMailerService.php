<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class UserMailerService
{
    public function __construct(private MailerInterface $mailer, private string $env)
    {
    }

    /**
     * @param array <string, mixed> $context
     *
     * @throws TransportExceptionInterface
     */
    public function sendTemplatedEmail(string $userEmail, string $subject, array $context, ?string $template = null, ?string $attachmentPath = null): void
    {
        if (null === $template) {
            $template = 'email.base.html.twig';
        }

        if (\in_array($this->env, ['dev', 'staging', 'test'], true)) {
            $subject = '[' . strtoupper($this->env) . '] ' . $subject;
        }

        $email = $this->getTemplatesEmail($userEmail, $subject, $context, $template);

        if (null !== $attachmentPath) {
            $email->attachFromPath($attachmentPath);
        }

        $this->mailer->send($email);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getTemplatesEmail(string $userEmail, string $subject, array $context, string $template): TemplatedEmail
    {
        return (new TemplatedEmail())
             ->to(new Address($userEmail))
             ->subject($subject)
             ->htmlTemplate($template)
             ->context($context)
        ;
    }
}
