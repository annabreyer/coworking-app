<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Withdrawal\WithdrawalResult;
use App\Entity\Withdrawal;
use App\Manager\WithdrawalManager;
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

    public function sendWithdrawalProcessedEmail(Withdrawal $withdrawal, WithdrawalResult $result): void
    {
        $instructions = $this->translator->trans('withdrawal.processed.instructions', $this->getWithdrawalParameters($withdrawal), 'email')
            . '<br><br>'
            . $this->translator->trans('withdrawal.processed.refund.' . $result->refundMethod, [
                '%amount%' => WithdrawalManager::formatAmount($result->refundAmount),
            ], 'email');

        if (0 < $result->valueCompensation) {
            $instructions .= '<br><br>' . $this->translator->trans('withdrawal.processed.value_compensation', [
                '%amount%' => WithdrawalManager::formatAmount($result->valueCompensation),
            ], 'email');
        }

        $this->sendWithdrawalResultEmail($withdrawal, 'withdrawal.processed', $instructions);
    }

    public function sendWithdrawalRejectedEmail(Withdrawal $withdrawal): void
    {
        $parameters             = $this->getWithdrawalParameters($withdrawal);
        $parameters['%reason%'] = nl2br(htmlspecialchars((string) $withdrawal->getAdminComment()));
        $instructions           = $this->translator->trans('withdrawal.rejected.instructions', $parameters, 'email');

        $this->sendWithdrawalResultEmail($withdrawal, 'withdrawal.rejected', $instructions);
    }

    private function sendWithdrawalResultEmail(Withdrawal $withdrawal, string $translationPrefix, string $instructions): void
    {
        $email = $withdrawal->getEmail();
        if (null === $email) {
            throw new \LogicException('Withdrawal must have an email to send the result.');
        }

        $subject = $this->translator->trans($translationPrefix . '.subject', [], 'email');

        $context = [
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $this->translator->trans('withdrawal.result.salutation', ['%name%' => $withdrawal->getName()], 'email'),
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $instructions,
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION  => $this->translator->trans('withdrawal.result.explanation', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE    => $this->translator->trans('withdrawal.result.signature', [], 'email'),
                self::EMAIL_STANDARD_ELEMENT_SUBJECT      => $subject,
            ],
        ];

        $this->userMailer->sendTemplatedEmail($email, $subject, $context);
    }

    /**
     * @return array<string, string>
     */
    private function getWithdrawalParameters(Withdrawal $withdrawal): array
    {
        $receivedAt = $withdrawal->getReceivedAt();
        if (null === $receivedAt) {
            throw new \LogicException('Withdrawal must have a reception date to send the result.');
        }

        return [
            '%date%'     => $receivedAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('d.m.Y'),
            '%contract%' => htmlspecialchars((string) $withdrawal->getContractReference()),
        ];
    }
}
