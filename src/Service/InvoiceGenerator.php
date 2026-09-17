<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Invoice\BookingPaymentSpecifics;
use App\Dto\Invoice\InvoiceLineItem;
use App\Dto\Invoice\InvoicePdfDocument;
use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Manager\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class InvoiceGenerator
{
    private const ASSETS_DIRECTORY = __DIR__ . '/../../templates/invoice/pdf/assets';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
        private readonly Filesystem $filesystem,
        private readonly string $invoiceDirectory,
        private readonly string $invoiceClientNumberPrefix,
    ) {
    }

    public function generateBookingInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getId()) {
            throw new \InvalidArgumentException('Invoice must be persisted.');
        }

        if (false === $invoice->getBookings()->first() || 1 < $invoice->getBookings()->count()) {
            throw new \InvalidArgumentException('Invoice must have exactly one booking.');
        }

        if (null === $invoice->getAmount()) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $invoiceBooking = $invoice->getFirstBooking();
        if (null === $invoiceBooking) {
            throw new \InvalidArgumentException('Invoice must have a booking.');
        }

        $user = $invoiceBooking->getUser();
        if (false === $user instanceof User) {
            throw new \InvalidArgumentException('Booking must have a user.');
        }

        $document = $this->buildBaseDocument($invoice, $user);
        $document->addItem($this->buildBookingItem($invoiceBooking));

        $paymentSpecifics = $this->buildBookingPaymentSpecifics($invoice);
        $document->addItems($paymentSpecifics->extraItems);
        $document->setTotal($this->formatAmount($paymentSpecifics->totalAmount));
        $document->setPaymentMessage($paymentSpecifics->paymentMessage);

        $this->render($invoice, $document);
    }

    public function generateVoucherInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getId()) {
            throw new \InvalidArgumentException('Invoice must be persisted.');
        }

        if (0 === $invoice->getVouchers()->count()) {
            throw new \InvalidArgumentException('Invoice has no vouchers.');
        }

        $firstVoucher = $invoice->getVouchers()->first();
        if (false === $firstVoucher instanceof Voucher) {
            throw new \InvalidArgumentException('Invoice has no vouchers.');
        }

        $voucherType = $firstVoucher->getVoucherType();
        if (null === $voucherType) {
            throw new \InvalidArgumentException('Voucher has no voucher type.');
        }

        $invoiceAmount = $invoice->getAmount();
        if (null === $invoiceAmount) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $user = $firstVoucher->getUser();
        if (false === $user instanceof User) {
            throw new \InvalidArgumentException('Voucher must have a user.');
        }

        $document = $this->buildBaseDocument($invoice, $user);
        $document->addItem(new InvoiceLineItem(
            position: 1,
            description: $this->translator->trans('invoice.description.voucher', [
                '%name%'           => $voucherType->getName(),
                '%validityMonths%' => $voucherType->getValidityMonths(),
            ], 'invoice'),
            amount: $this->formatAmount($invoiceAmount / 100),
            codes: $this->getVoucherCodes($invoice),
        ));

        if ($invoice->isFullyPaidByPayPal()) {
            $document->setTotal($this->formatAmount($invoiceAmount / 100));
            $document->setPaymentMessage($this->getAlreadyPaidMessage($invoice));
        }

        if (false === $invoice->isFullyPaid()) {
            $document->setTotal($this->formatAmount($invoiceAmount / 100));
            $document->setPaymentMessage($this->getDueMessage($invoice));
        }

        $this->render($invoice, $document);
    }

    public function generateGeneralInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getId()) {
            throw new \InvalidArgumentException('Invoice must be persisted.');
        }

        $invoiceAmount = $invoice->getAmount();
        if (null === $invoiceAmount) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $user = $invoice->getUser();
        if (false === $user instanceof User) {
            throw new \InvalidArgumentException('Invoice must have a user.');
        }

        $document = $this->buildBaseDocument($invoice, $user);
        $document->addItem(new InvoiceLineItem(
            position: 1,
            description: (string) $invoice->getDescription(),
            amount: $this->formatAmount($invoiceAmount / 100),
        ));
        $document->setTotal($this->formatAmount($invoiceAmount / 100));
        $document->setPaymentMessage($this->getDueMessage($invoice));

        $this->render($invoice, $document);
    }

    public function getTargetDirectory(Invoice $invoice): string
    {
        if (null === $invoice->getDate()) {
            throw new \InvalidArgumentException('Invoice must have a date.');
        }

        $year  = $invoice->getDate()->format('Y');
        $month = $invoice->getDate()->format('m');

        $targetDirectory = $this->invoiceDirectory . '/' . $year . '/' . $month;
        if (false === $this->filesystem->exists($targetDirectory)) {
            $this->filesystem->mkdir($targetDirectory);
        }

        return $targetDirectory;
    }

    private function buildBaseDocument(Invoice $invoice, User $user): InvoicePdfDocument
    {
        if (null === $invoice->getNumber()) {
            throw new \InvalidArgumentException('Invoice must have a number.');
        }

        if (null === $invoice->getDate()) {
            throw new \InvalidArgumentException('Invoice must have a date.');
        }

        if (null === $user->getId()) {
            throw new \InvalidArgumentException('User must be persisted.');
        }

        $hasAddress = $user->hasAddress();

        return new InvoicePdfDocument(
            headerLogo: $this->getAssetDataUri('header_logo.png'),
            vielenDank: $this->getAssetDataUri('vielen_dank.png'),
            invoiceNumber: $invoice->getNumber(),
            invoiceDate: $invoice->getDate()->format('d.m.Y'),
            clientNumber: $this->invoiceClientNumberPrefix . InvoiceManager::getClientNumber($user->getId()),
            clientName: $user->getFullName(),
            invoiceType: $this->translator->trans(
                $invoice->isRefund() ? 'invoice.type.refund' : 'invoice.type.invoice',
                [],
                'invoice'
            ),
            introText: $this->translator->trans(
                $invoice->isRefund() ? 'invoice.intro.refund' : 'invoice.intro.invoice',
                [],
                'invoice'
            ),
            clientStreet: $hasAddress ? $user->getStreet() : null,
            clientPostCodeAndCity: $hasAddress ? $user->getPostCode() . ' ' . $user->getCity() : null,
            clientEmail: $hasAddress ? null : ($user->getEmail() ?? ''),
        );
    }

    private function buildBookingItem(Booking $booking): InvoiceLineItem
    {
        $bookingAmount = $booking->getAmount();
        if (null === $bookingAmount) {
            throw new \InvalidArgumentException('Booking must have an amount.');
        }

        $bookingDate = $booking->getBusinessDay()?->getDate();
        if (null === $bookingDate) {
            throw new \InvalidArgumentException('Booking must have a business day with a date.');
        }

        $bookingRoom = $booking->getRoom();
        if (null === $bookingRoom) {
            throw new \InvalidArgumentException('Booking must have a room.');
        }

        return new InvoiceLineItem(
            position: 1,
            description: $this->translator->trans('invoice.description.booking', [
                '%date%' => $bookingDate->format('d.m.Y'),
                '%room%' => $bookingRoom->getName(),
            ], 'invoice'),
            amount: $this->formatAmount($bookingAmount / 100),
        );
    }

    private function buildBookingPaymentSpecifics(Invoice $invoice): BookingPaymentSpecifics
    {
        if ($invoice->isFullyPaidByVoucher()) {
            return new BookingPaymentSpecifics(
                extraItems: $this->buildVoucherPaymentItems($invoice),
                totalAmount: 0.0,
                paymentMessage: null,
            );
        }

        if ($invoice->isFullyPaidByPayPal()) {
            return new BookingPaymentSpecifics(
                extraItems: [],
                totalAmount: $invoice->getAmount() / 100,
                paymentMessage: $this->getAlreadyPaidMessage($invoice),
            );
        }

        return new BookingPaymentSpecifics(
            extraItems: [],
            totalAmount: $invoice->getAmount() / 100,
            paymentMessage: $this->getDueMessage($invoice),
        );
    }

    /**
     * @return InvoiceLineItem[]
     */
    private function buildVoucherPaymentItems(Invoice $invoice): array
    {
        if ($invoice->getPayments()->isEmpty()) {
            return [];
        }

        if (false === $invoice->isFullyPaidByVoucher()) {
            return [];
        }

        $items    = [];
        $position = 2;
        foreach ($invoice->getPayments() as $payment) {
            $paymentVoucher = $payment->getVoucher();
            if (null === $paymentVoucher || null === $paymentVoucher->getCode()) {
                throw new \InvalidArgumentException('Payment must have a voucher and a voucher code.');
            }

            $items[] = new InvoiceLineItem(
                position: $position,
                description: $this->translator->trans('invoice.payment.voucher', [
                    '%voucherCode%' => $paymentVoucher->getCode(),
                ], 'invoice'),
                amount: '-' . $this->formatAmount($payment->getAmount() / 100),
            );
            ++$position;
        }

        return $items;
    }

    private function getVoucherCodes(Invoice $invoice): string
    {
        return implode(', ', $invoice->getVouchers()->map(static fn (Voucher $voucher) => $voucher->getCode())->toArray());
    }

    private function getDueMessage(Invoice $invoice): ?string
    {
        if ($invoice->isFullyPaidByVoucher() || $invoice->isFullyPaidByPayPal()) {
            return null;
        }

        if ($invoice->isBookingInvoice()) {
            return $this->translator->trans('invoice.due.booking', [], 'invoice');
        }

        return $this->translator->trans('invoice.due.general', [], 'invoice');
    }

    private function getAlreadyPaidMessage(Invoice $invoice): string
    {
        if (false === $invoice->isFullyPaidByPayPal()) {
            throw new \InvalidArgumentException('Invoice must be fully paid by PayPal.');
        }

        $payPalPayment = $invoice->getPayments()->first();
        if (false === $payPalPayment instanceof Payment || false === $payPalPayment->isPayPalPayment()) {
            throw new \InvalidArgumentException('Already paid invoice must have a PayPal payment.');
        }

        $paymentDate = $payPalPayment->getDate();
        if (null === $paymentDate) {
            throw new \InvalidArgumentException('Already paid invoice must have a payment date.');
        }

        return $this->translator->trans('invoice.payment.paid', ['%date%' => $paymentDate->format('d.m.Y')], 'invoice');
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, ',', '') . ' €';
    }

    private function getAssetDataUri(string $fileName): string
    {
        $path = self::ASSETS_DIRECTORY . '/' . $fileName;
        $data = file_get_contents($path);
        if (false === $data) {
            throw new \RuntimeException(\sprintf('Could not read invoice asset "%s".', $path));
        }

        return 'data:image/png;base64,' . base64_encode($data);
    }

    private function render(Invoice $invoice, InvoicePdfDocument $document): void
    {
        $html = $this->twig->render('invoice/pdf/invoice.html.twig', $document->toArray());

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $this->saveInvoice($invoice, $dompdf);
    }

    private function saveInvoice(Invoice $invoice, Dompdf $dompdf): void
    {
        $fileName = $this->getTargetDirectory($invoice) . '/' . $invoice->getNumber() . '.pdf';

        $this->filesystem->dumpFile($fileName, $dompdf->output());

        $invoice->setFilePath($fileName);
        $this->entityManager->flush();
    }
}
