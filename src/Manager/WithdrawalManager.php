<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\Withdrawal\WithdrawalResult;
use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\Withdrawal;
use App\Repository\InvoiceRepository;
use App\Service\AdminMailerService;
use App\Service\WithdrawalMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly BookingManager $bookingManager,
        private readonly VoucherManager $voucherManager,
        private readonly RefundManager $refundManager,
        private readonly TranslatorInterface $translator,
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

    public static function canBeProcessed(Withdrawal $withdrawal): bool
    {
        return Withdrawal::STATUS_RECEIVED === $withdrawal->getStatus() && null !== $withdrawal->getInvoice();
    }

    public function processWithdrawal(Withdrawal $withdrawal, ?string $adminComment = null, bool $todaysBookingUnused = false): WithdrawalResult
    {
        if (Withdrawal::STATUS_RECEIVED !== $withdrawal->getStatus()) {
            throw new \LogicException('Only received withdrawals can be processed.');
        }

        $invoice = $withdrawal->getInvoice();
        if (null === $invoice) {
            throw new \LogicException('Withdrawal must have an invoice to be processed.');
        }

        if ($invoice->isRefund()) {
            throw new \LogicException('Reversal invoices can not be withdrawn.');
        }

        if (0 < $invoice->getPayments()->count() && 0 < $invoice->getRemainingAmount()) {
            throw new \LogicException('Partially paid invoices must be refunded manually.');
        }

        if ($invoice->isBookingInvoice()) {
            $result = $this->processBookingWithdrawal($invoice, $todaysBookingUnused);
        } elseif ($invoice->isVoucherInvoice()) {
            $result = $this->processVoucherWithdrawal($invoice);
        } else {
            throw new \LogicException('Only booking and voucher invoices can be processed automatically.');
        }

        $adminComment = null === $adminComment || '' === trim($adminComment) ? null : trim($adminComment);

        $withdrawal->setStatus($result->status)
                   ->setAdminComment($adminComment ?? $this->getDefaultAdminComment($result))
        ;
        $this->entityManager->flush();

        try {
            if (Withdrawal::STATUS_REJECTED === $result->status) {
                $this->withdrawalMailerService->sendWithdrawalRejectedEmail($withdrawal);
            } else {
                $this->withdrawalMailerService->sendWithdrawalProcessedEmail($withdrawal, $result);
            }
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Withdrawal result could not be sent. ' . $exception->getMessage(), ['withdrawal' => $withdrawal->getId()]);
        }

        return $result;
    }

    public function getDefaultAdminComment(WithdrawalResult $result): string
    {
        if (null !== $result->rejectionReason) {
            return $this->translator->trans('withdrawal.result.reason.' . $result->rejectionReason, [], 'email');
        }

        return $this->translator->trans('admin.withdrawal.processed.' . $result->refundMethod, [
            '%amount%'            => self::formatAmount($result->refundAmount),
            '%valueCompensation%' => self::formatAmount($result->valueCompensation),
        ], 'flash');
    }

    public static function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, ',', '.');
    }

    private function processBookingWithdrawal(Invoice $invoice, bool $todaysBookingUnused): WithdrawalResult
    {
        $bookings = $invoice->getBookings()->filter(static function (Booking $booking) {
            return false === $booking->isCancelled();
        });

        if (0 === $bookings->count()) {
            return new WithdrawalResult(Withdrawal::STATUS_REJECTED, rejectionReason: WithdrawalResult::REJECTION_ALREADY_CANCELLED);
        }

        $today = $this->now()->format('Y-m-d');
        foreach ($bookings as $booking) {
            $businessDay = $booking->getBusinessDay();
            if (null === $businessDay) {
                throw new \LogicException('Booking must have a business day.');
            }

            $bookingDate = $businessDay->getDate()->format('Y-m-d');
            if ($bookingDate < $today || ($bookingDate === $today && false === $todaysBookingUnused)) {
                return new WithdrawalResult(Withdrawal::STATUS_REJECTED, rejectionReason: WithdrawalResult::REJECTION_PERFORMED);
            }
        }

        foreach ($bookings as $booking) {
            $this->bookingManager->cancelBookingAfterWithdrawal($booking);
        }

        return $this->refund($invoice, (int) $invoice->getAmount(), 0);
    }

    private function processVoucherWithdrawal(Invoice $invoice): WithdrawalResult
    {
        $vouchers       = $invoice->getVouchers();
        $unusedVouchers = $vouchers->filter(static function (Voucher $voucher) {
            return null === $voucher->getUseDate();
        });

        if (0 === $unusedVouchers->count()) {
            return new WithdrawalResult(Withdrawal::STATUS_REJECTED, rejectionReason: WithdrawalResult::REJECTION_VOUCHERS_USED);
        }

        $usedCount = $vouchers->count() - $unusedVouchers->count();
        if (0 < $usedCount && 0 === $invoice->getPayments()->count()) {
            throw new \LogicException('Vouchers of an unpaid invoice have been used, the withdrawal must be handled manually.');
        }

        $amount            = (int) $invoice->getAmount();
        $valueCompensation = intdiv($amount * $usedCount, $vouchers->count());

        foreach ($unusedVouchers as $voucher) {
            $this->voucherManager->invalidateVoucher($voucher);
        }

        return $this->refund($invoice, $amount - $valueCompensation, $valueCompensation);
    }

    private function refund(Invoice $invoice, int $amount, int $valueCompensation): WithdrawalResult
    {
        if (0 >= $amount) {
            return new WithdrawalResult(Withdrawal::STATUS_PROCESSED, valueCompensation: $valueCompensation);
        }

        $payments = $invoice->getPayments();
        if (0 === $payments->count()) {
            $this->refundManager->refundInvoiceAfterWithdrawal($invoice, $amount);

            return new WithdrawalResult(Withdrawal::STATUS_PROCESSED, WithdrawalResult::REFUND_METHOD_REVERSAL, 0, $valueCompensation);
        }

        $paidByPayPal = $payments->exists(static function (int $key, Payment $payment) {
            return $payment->isPayPalPayment();
        });

        $moneyRefund = $this->refundManager->refundInvoiceAfterWithdrawal($invoice, $amount);
        if (0 === $moneyRefund) {
            return new WithdrawalResult(Withdrawal::STATUS_PROCESSED, WithdrawalResult::REFUND_METHOD_VOUCHER, 0, $valueCompensation);
        }

        $refundMethod = $paidByPayPal ? WithdrawalResult::REFUND_METHOD_PAYPAL : WithdrawalResult::REFUND_METHOD_TRANSACTION;

        return new WithdrawalResult(Withdrawal::STATUS_PROCESSED, $refundMethod, $moneyRefund, $valueCompensation);
    }
}
