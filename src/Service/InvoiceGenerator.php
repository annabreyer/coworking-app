<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Manager\InvoiceManager;
use setasign\Fpdi\Tfpdf\Fpdi;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceGenerator
{
    private Fpdi $pdf;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Filesystem $filesystem,
        private readonly string $invoiceTemplatePath,
        private readonly string $invoiceDirectory,
        private readonly string $invoiceClientNumberPrefix
    ) {
        $this->pdf = new Fpdi();
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

        $this->setupInvoiceTemplate();
        $this->addInvoiceData($invoice);
        $this->addClientData($user);
        $this->writeBookingLine($invoiceBooking);

        if ($invoice->isFullyPaidByVoucher()) {
            $this->addVoucherPayment($invoice);
            $this->writeTotalAmount(0);
        }

        if ($invoice->isFullyPaidByPayPal()) {
            $this->writeTotalAmount($invoice->getAmount()/100);
            $this->addAlreadyPaidMention($invoice);
        }

        if (false === $invoice->isFullyPaid()) {
            $this->writeTotalAmount($invoice->getAmount()/100);
            $this->addDueMention($invoice);
        }

        $this->saveInvoice($invoice);
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

        $this->setupInvoiceTemplate();
        $this->addInvoiceData($invoice);
        $this->addClientData($user);
        $this->writeFirstPositionNumber();
        $this->writeVoucherDescription($voucherType);
        $this->writeVoucherCodes($invoice);
        $this->writeAmount($invoiceAmount/100);
        $this->writeTotalAmount($invoiceAmount/100);
        $this->addDueMention($invoice);

        $this->saveInvoice($invoice);
    }

    public function generateGeneralInvoice(Invoice $invoice): void
    {
        if (null === $invoice->getId()) {
            throw new \InvalidArgumentException('Invoice must be persisted.');
        }

        if (0 === $invoice->getAmount()) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $user = $invoice->getUser();
        if (false === $user instanceof User) {
            throw new \InvalidArgumentException('Invoice must have a user.');
        }

        $invoiceAmount = $invoice->getAmount();
        if (null === $invoiceAmount) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $this->setupInvoiceTemplate();
        $this->addInvoiceData($invoice);
        $this->addClientData($user);
        $this->writeFirstPositionNumber();
        $this->writeValue(30, 145, 140, 8, (string) $invoice->getDescription());
        $this->writeAmount($invoiceAmount/100);
        $this->writeTotalAmount($invoiceAmount/100);
        $this->addDueMention($invoice);

        $this->saveInvoice($invoice);
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

    private function setupInvoiceTemplate(): void
    {
        $this->setStandardFont();
        $this->pdf->AddPage();
        $this->pdf->setSourceFile($this->invoiceTemplatePath);

        $template = $this->pdf->importPage(1);

        $this->pdf->useTemplate($template, ['adjustPageSize' => true]);
    }

    private function addInvoiceData(Invoice $invoice): void
    {
        if (null === $invoice->getNumber()) {
            throw new \InvalidArgumentException('Invoice must have a number.');
        }

        $this->writeInvoiceNumber($invoice);
        $this->writeInvoiceDate($invoice);
    }

    private function addClientData(User $user): void
    {
        $this->writeClientNumber($user);
        $this->writeClientFullName($user);

        if ($user->hasAddress()) {
            $this->writeClientStreet($user);
            $this->writeClientCity($user);
        }
    }

    private function writeInvoiceNumber(Invoice $invoice): void
    {
        if (null === $invoice->getNumber()) {
            throw new \InvalidArgumentException('Invoice must have a number.');
        }

        $this->writeValue(160, 45.5, 30, 8, $invoice->getNumber());
    }

    private function writeClientNumber(User $user): void
    {
        if (null === $user->getId()) {
            throw new \InvalidArgumentException('User must be persisted.');
        }

        $number       = InvoiceManager::getClientNumber($user->getId());
        $clientNumber = $this->invoiceClientNumberPrefix . $number;

        $this->writeValue(160, 51, 30, 8, $clientNumber);
    }

    private function writeInvoiceDate(Invoice $invoice): void
    {
        if (null === $invoice->getDate()) {
            throw new \InvalidArgumentException('Invoice must have a date.');
        }

        $this->writeValue(160, 56.25, 30, 8, $invoice->getDate()->format('d.m.Y'));
    }

    private function writeClientFullName(User $user): void
    {
        $this->writeValue(13, 85, 100, 8, $user->getFullName());
    }

    private function writeClientStreet(User $user): void
    {
        $this->writeValue(13, 90, 100, 8, $user->getStreet());
    }

    private function writeClientCity(User $user): void
    {
        $postCodeAndCity = $user->getPostCode() . ' ' . $user->getCity();
        $this->writeValue(13, 95, 100, 8, $postCodeAndCity);
    }

    private function writeBookingLine(Booking $booking): void
    {
        $bookingAmount = $booking->getAmount();
        if (null === $bookingAmount) {
            throw new \InvalidArgumentException('Booking must have an amount.');
        }

        $this->writeFirstPositionNumber();
        $this->writeBookingDescription($booking);
        $this->writeAmount($bookingAmount/100);
    }

    private function writeFirstPositionNumber(): void
    {
        $this->writeValue(15, 145, 10, 8, '1');
    }

    private function writeBookingDescription(Booking $booking): void
    {
        $bookingDate = $booking->getBusinessDay()?->getDate();
        if (null === $bookingDate) {
            throw new \InvalidArgumentException('Booking must have a business day with a date.');
        }

        $bookingRoom = $booking->getRoom();
        if (null === $bookingRoom) {
            throw new \InvalidArgumentException('Booking must have a room.');
        }

        $description = $this->translator->trans('booking.invoice.description', [
            '%date%' => $bookingDate->format('d.m.Y'),
            '%room%' => $bookingRoom->getName(),
        ]);
        $this->writeValue(30, 145, 140, 8, $description);
    }

    private function writeVoucherDescription(VoucherType $voucherType): void
    {
        $description = $this->translator->trans('voucher.invoice.description', [
            '%units%'          => $voucherType->getUnits(),
            '%validityMonths%' => $voucherType->getValidityMonths(),
        ]);

        $this->writeValue(30, 145, 140, 8, $description);
    }

    private function writeVoucherCodes(Invoice $invoice): void
    {
        $codes = implode(', ', $invoice->getVouchers()->map(static fn (Voucher $voucher) => $voucher->getCode())->toArray());
        $this->writeValue(30, 155, 120, 5, $codes);
    }

    private function writeAmount(float $amount): void
    {
        $invoiceAmount = number_format($amount, 2, ',', '');

        $this->writeValue(180, 145, 30, 8, $invoiceAmount . ' €');
    }

    private function writeTotalAmount(float $amount): void
    {
        $invoiceAmount = number_format($amount, 2, ',', '');

        $this->writeValue(180, 182, 30, 8, $invoiceAmount . ' €');
    }

    private function addVoucherPayment(Invoice $invoice): void
    {
        if ($invoice->getPayments()->isEmpty()) {
            return;
        }

        if (false === $invoice->isFullyPaidByVoucher()) {
            return;
        }

        $y        = 155;
        $position = 2;
        foreach ($invoice->getPayments() as $payment) {
            $paymentVoucher = $payment->getVoucher();
            if (null === $paymentVoucher || null === $paymentVoucher->getCode()) {
                throw new \InvalidArgumentException('Payment must have a voucher and a voucher code.');
            }

            $paymentMethodMessage = $this->translator->trans('booking.invoice.paid_by_voucher', [
                '%voucherCode%' => $paymentVoucher->getCode(),
            ]);
            $amount = $payment->getAmount() / 100;

            $this->writeValue(15, $y, 10, 8, (string) $position);
            $this->writeValue(30, $y, 140, 8, $paymentMethodMessage);
            $this->writeValue(180, $y, 30, 8, '-' . $amount . ',00 €');
            $y += 12;
            ++$position;
        }
    }

    private function addDueMention(Invoice $invoice): void
    {
        if ($invoice->isFullyPaid()) {
            return;
        }

        if ($invoice->isBookingInvoice()) {
            $dueMessage = $this->translator->trans('booking.invoice.due');
        } else {
            $dueMessage = $this->translator->trans('voucher.invoice.due');
        }

        $this->setBoldFont();
        $this->writeValue(15, 210, 200, 8, $dueMessage);
        $this->setStandardFont();
    }

    private function addAlreadyPaidMention(Invoice $invoice): void
    {
        if (false === $invoice->isFullyPaidByPayPal()) {
            return;
        }

        $payPalPayment = $invoice->getPayments()->first();
        if (false === $payPalPayment instanceof Payment || false === $payPalPayment->isPayPalPayment()) {
            throw new \InvalidArgumentException('Already paid invoice must have a PayPal payment.');
        }

        $paymentDate = $payPalPayment->getDate();
        if (null === $paymentDate) {
            throw new \InvalidArgumentException('Already paid invoice must have a payment date.');
        }

        $dueMessage = $this->translator->trans('booking.invoice.already_paid', ['%date%' => $paymentDate->format('d.m.Y')]);
        $this->setBoldFont();
        $this->writeValue(15, 210, 200, 8, $dueMessage);
        $this->setStandardFont();
    }

    private function saveInvoice(Invoice $invoice): void
    {
        $fileName = $this->getTargetDirectory($invoice) . '/' . $invoice->getNumber() . '.pdf';

        $this->pdf->Output('F', $fileName, true);
    }

    private function writeValue(float $x, float $y, int $w, int $h, string $value): void
    {
        $this->pdf->SetXY($x, $y);
        $this->pdf->multiCell($w, $h, mb_convert_encoding($value, 'windows-1252', 'UTF-8'));
    }

    private function setStandardFont(): void
    {
        $this->pdf->SetFont('Helvetica', '', 12);
    }

    private function setBoldFont(): void
    {
        $this->pdf->SetFont('Helvetica', 'b', 12);
    }
}
