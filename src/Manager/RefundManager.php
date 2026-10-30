<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Invoice;
use App\Entity\Payment;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class RefundManager
{
    use ClockAwareTrait;

    public function __construct(
        private readonly VoucherManager $voucherManager,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceManager $invoiceManager,
    ) {
    }

    public function refundInvoiceWithVoucher(Invoice $invoice): void
    {
        if (0 === $invoice->getPayments()->count()) {
            throw new \LogicException('Only invoices with payments can be refunded by voucher. Use refundWithReversalInvoice instead.');
        }

        if ($invoice->isFullyPaidByVoucher()) {
            $this->refundInvoiceVoucherPayments($invoice->getPayments());

            return;
        }

        $this->voucherManager->createRefundVoucher($invoice);
    }

    public function refundInvoiceVoucherPayments(Collection $payments): void
    {
        $voucherPayments = $payments->filter(static function (Payment $payment) {
            return Payment::PAYMENT_TYPE_VOUCHER === $payment->getType();
        });

        if (0 === $voucherPayments->count()) {
            throw new \InvalidArgumentException('There must at least be one voucher payment to be refunded.');
        }

        foreach ($voucherPayments as $voucherPayment) {
            if (null === $voucherPayment->getVoucher()) {
                throw new \LogicException('Voucher Payment needs a voucher attached.');
            }

            $this->voucherManager->resetExpiryDate($voucherPayment->getVoucher());
        }
    }

    public function refundInvoiceWithReversalInvoice(Invoice $invoice): void
    {
        if (0 >= $invoice->getRemainingAmount()) {
            throw new \LogicException('Invoice must have a positive remaining amount to be refunded with a reversal invoice.');
        }

        if (0 !== $invoice->getPayments()->count()) {
            throw new \LogicException('Invoice can not have payments for a reversal invoice. Refund by voucher instead.');
        }

        $this->addRefundPaymentToInvoice($invoice);

        $this->invoiceManager->processReversalInvoice($invoice);
    }

    public function addRefundPaymentToInvoice(Invoice $invoice): void
    {
        if (0 >= $invoice->getRemainingAmount()) {
            throw new \LogicException('Invoice must have a positive remaining amount to get a refund payment.');
        }

        $refundPayment = $this->createRefundPaymentForInvoice($invoice);
        $invoice->addPayment($refundPayment);

        $this->entityManager->flush();
    }

    public function createRefundPaymentForInvoice(Invoice $invoice): Payment
    {
        if (0 >= $invoice->getRemainingAmount()) {
            throw new \LogicException('Invoice must have a positive remaining amount to get a refund payment.');
        }

        $description = $this->translator->trans('invoice.description.cancel', ['%invoiceNumber%' => $invoice->getNumber()], 'invoice');

        $refundPayment = new Payment();
        $refundPayment->setAmount($invoice->getRemainingAmount())
                               ->setDate($this->now())
                               ->setInvoice($invoice)
                               ->setComment($description)
        ->setType(Payment::PAYMENT_TYPE_REFUND);

        return $refundPayment;
    }

    public function refundInvoiceAfterWithdrawal(Invoice $invoice, int $amount): int
    {
        if (0 >= $amount || $amount > (int) $invoice->getAmount()) {
            throw new \InvalidArgumentException('Refund amount must be positive and not higher than the invoice amount.');
        }

        $payments = $invoice->getPayments();
        if (0 === $payments->count()) {
            if ($amount !== $invoice->getAmount()) {
                throw new \LogicException('Unpaid invoices can only be cancelled completely.');
            }

            $this->refundInvoiceWithReversalInvoice($invoice);

            return 0;
        }

        if (0 < $invoice->getRemainingAmount()) {
            throw new \LogicException('Partially paid invoices must be refunded manually.');
        }

        $voucherPayments = $payments->filter(static function (Payment $payment) {
            return $payment->isVoucherPayment();
        });

        if (0 < $voucherPayments->count()) {
            $this->refundInvoiceVoucherPayments($voucherPayments);
        }

        $moneyRefund = min($amount, self::getMoneyPaidAmount($invoice));
        if (0 < $moneyRefund) {
            $this->refundPaidInvoiceWithReversalInvoice($invoice, $moneyRefund);
        }

        return $moneyRefund;
    }

    public function refundPaidInvoiceWithReversalInvoice(Invoice $invoice, int $amount): Invoice
    {
        $user = $invoice->getUser();
        if (null === $user) {
            throw new \LogicException('Invoice must have a user.');
        }

        if (0 >= $amount || $amount > self::getMoneyPaidAmount($invoice)) {
            throw new \InvalidArgumentException('Refund amount must be positive and not higher than the amount paid by PayPal or transfer.');
        }

        $description     = $this->translator->trans('invoice.description.withdrawal', ['%invoiceNumber%' => $invoice->getNumber()], 'invoice');
        $reversalInvoice = $this->invoiceManager->createInvoice($user, -1 * $amount);
        $reversalInvoice->setDescription($description);

        $refundPayment = new Payment();
        $refundPayment->setAmount(-1 * $amount)
                      ->setDate($this->now())
                      ->setInvoice($reversalInvoice)
                      ->setComment($description)
                      ->setType(Payment::PAYMENT_TYPE_REFUND)
        ;
        $reversalInvoice->addPayment($refundPayment);

        $this->invoiceManager->saveInvoice($reversalInvoice);
        $this->invoiceManager->generateInvoicePdf($reversalInvoice);

        return $reversalInvoice;
    }

    public static function getMoneyPaidAmount(Invoice $invoice): int
    {
        $amount = 0;
        foreach ($invoice->getPayments() as $payment) {
            if ($payment->isPayPalPayment() || $payment->isTransactionPayment()) {
                $amount += $payment->getAmount();
            }
        }

        return $amount;
    }
}
