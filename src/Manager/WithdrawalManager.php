<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Withdrawal;
use App\Repository\InvoiceRepository;
use App\Service\AdminMailerService;
use App\Service\WithdrawalMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class WithdrawalManager
{
    use ClockAwareTrait;

    public const WITHDRAWAL_PERIOD_DAYS = 14;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly WithdrawalMailerService $withdrawalMailerService,
        private readonly AdminMailerService $adminMailerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function saveWithdrawal(string $name, string $email, string $contractReference, ?User $user): Withdrawal
    {
        $invoice = $this->findInvoiceForWithdrawal($contractReference, $email, $user);

        $withdrawal = new Withdrawal();
        $withdrawal
            ->setName($name)
            ->setEmail($email)
            ->setContractReference($contractReference)
            ->setReceivedAt($this->now())
            ->setInvoice($invoice)
            ->setUser($user ?? $invoice?->getUser())
        ;

        $this->entityManager->persist($withdrawal);
        $this->entityManager->flush();

        try {
            $this->withdrawalMailerService->sendWithdrawalReceipt($withdrawal);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Withdrawal receipt could not be sent. ' . $exception->getMessage(), ['withdrawal' => $withdrawal->getId()]);
        }

        try {
            $this->adminMailerService->notifyAdminAboutWithdrawal($withdrawal);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Admin could not be notified about withdrawal. ' . $exception->getMessage(), ['withdrawal' => $withdrawal->getId()]);
        }

        return $withdrawal;
    }

    public function findInvoiceForWithdrawal(string $contractReference, string $email, ?User $user): ?Invoice
    {
        $invoice = $this->invoiceRepository->findOneBy(['number' => strtoupper(trim($contractReference))]);
        if (null === $invoice) {
            return null;
        }

        $invoiceUser = $invoice->getUser();
        if (null === $invoiceUser) {
            return null;
        }

        if (null !== $user && $user === $invoiceUser) {
            return $invoice;
        }

        if (0 === strcasecmp((string) $invoiceUser->getEmail(), trim($email))) {
            return $invoice;
        }

        return null;
    }

    /**
     * @return Invoice[]
     */
    public function getInvoicesWithinWithdrawalPeriod(User $user): array
    {
        $limit = $this->now()->modify('-' . self::WITHDRAWAL_PERIOD_DAYS . ' days')->setTime(0, 0);

        return array_values(array_filter(
            $user->getInvoices()->toArray(),
            static function (Invoice $invoice) use ($limit) {
                return null !== $invoice->getDate()
                    && $invoice->getDate() >= $limit
                    && false === $invoice->isRefund();
            }
        ));
    }
}
