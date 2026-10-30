<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Withdrawal;
use App\Trait\EmailContextTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class WithdrawalMailerService
{
    use EmailContextTrait;

    public const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private readonly UserMailerService $userMailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function sendWithdrawalReceipt(Withdrawal $withdrawal): void
    {
        $email      = $withdrawal->getEmail();
        $receivedAt = $withdrawal->getReceivedAt();
        if (null === $email || null === $receivedAt) {
            throw new \LogicException('Withdrawal must have an email and a reception date to send the receipt.');
        }

        $receivedAt = $receivedAt->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $subject    = $this->translator->trans('withdrawal.receipt.subject', [], 'email');

        $context = [
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $this->translator->trans('withdrawal.receipt.salutation', ['%name%' => $withdrawal->getName()], 'email'),
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $this->translator->trans('withdrawal.receipt.instructions', [
                    '%date%'     => $receivedAt->format('d.m.Y'),
                    '%time%'     => $receivedAt->format('H:i:s'),
                    '%name%'     => htmlspecialchars((string) $withdrawal->getName()),
                    '%email%'    => htmlspecialchars($email),
                    '%contract%' => htmlspecialchars((string) $withdrawal->getContractReference()),
                ], 'email'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION => $this->translator->trans('withdrawal.receipt.explanation', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE   => $this->translator->trans('withdrawal.receipt.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT     => $subject,
            ],
        ];

        $this->userMailer->sendTemplatedEmail($email, $subject, $context);
    }
}
