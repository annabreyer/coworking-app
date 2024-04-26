<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\Price;
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
            throw new \InvalidArgumentException('Invoice must have at exactly one booking.');
        }

        if (null === $invoice->getAmount()) {
            throw new \InvalidArgumentException('Invoice must have an amount.');
        }

        $this->setupInvoiceTemplate();
        $this->addInvoiceData($invoice);
        $this->addClientData($invoice);
        $this->writeBookingLine($invoice->getBookings()->first());

        if (false === $invoice->isFullyPaid()) {
            $this->writeTotalAmount($invoice->getAmount());
            $this->addDueMention($invoice);
        }

        if ($invoice->isFullyPaidByVoucher()) {
            $this->addVoucherPayment($invoice);
        }

        if (false === $invoice->getPayments()->isEmpty()) {
            $this->writeTotalAmount($invoice->getAmount());
        }

        $this->saveInvoice($invoice);
    }

    public function generateVoucherInvoice(Invoice $invoice, Price $voucherPrice): void
    {
        if (null === $invoice->getId()) {
            throw new \InvalidArgumentException('Invoice must be persisted.');
        }

        if (false === $voucherPrice->isVoucher()) {
            throw new \InvalidArgumentException('Price must be a voucher.');
        }

        if (null === $voucherPrice->getVoucherType()) {
            throw new \InvalidArgumentException('Price must have a voucher type.');
        }

        if ($voucherPrice->getVoucherType()->getUnits() !== $invoice->getVouchers()->count()) {
            throw new \InvalidArgumentException('Voucher count does not match voucher type.');
        }

        $this->setupInvoiceTemplate();
        $this->addInvoiceData($invoice);
        $this->addClientData($invoice);
        $this->writeFirstPositionNumber();
        $this->writeVoucherDescription($voucherPrice->getVoucherType());
        $this->writeAmount($invoice->getAmount());
        $this->writeTotalAmount($invoice->getAmount());
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
        $this->writeInvoiceNumber($invoice);
        $this->writeClientNumber($invoice);
        $this->writeInvoiceDate($invoice);
    }

    private function addClientData(Invoice $invoice): void
    {
        $this->writeClientFullName($invoice);

        if ($invoice->getUser()->hasAddress()) {
            $this->writeClientStreet($invoice);
            $this->writeClientCity($invoice);
        }
    }

    private function writeInvoiceNumber(Invoice $invoice): void
    {
        $this->writeValue(160, 45.5, 30, 8, $invoice->getNumber());
    }

    private function writeClientNumber(Invoice $invoice): void
    {
        $number       = InvoiceManager::getClientNumber($invoice->getUser()->getId());
        $clientNumber = $this->invoiceClientNumberPrefix . $number;

        $this->writeValue(160, 51, 30, 8, $clientNumber);
    }

    private function writeInvoiceDate(Invoice $invoice): void
    {
        $this->writeValue(160, 56.25, 30, 8, $invoice->getDate()->format('d.m.Y'));
    }

    private function writeClientFullName(Invoice $invoice): void
    {
        $this->writeValue(13, 85, 100, 8, $invoice->getUser()->getFullName());
    }

    private function writeClientStreet(Invoice $invoice): void
    {
        $this->writeValue(13, 90, 100, 8, $invoice->getUser()->getStreet());
    }

    private function writeClientCity(Invoice $invoice): void
    {
        $postCodeAndCity = $invoice->getUser()->getPostCode() . ' ' . $invoice->getUser()->getCity();
        $this->writeValue(13, 95, 100, 8, $postCodeAndCity);
    }

    private function writeBookingLine(Booking $booking): void
    {
        $this->writeFirstPositionNumber();
        $this->writeBookingDescription($booking);
        $this->writeAmount($booking->getAmount());
    }

    private function writeFirstPositionNumber(): void
    {
        $this->writeValue(15, 145, 10, 8, '1');
    }

    private function writeBookingDescription(Booking $booking): void
    {
        if (null === $booking->getBusinessDay() || null === $booking->getBusinessDay()->getDate()) {
            throw new \InvalidArgumentException('Booking must have a business day with a date.');
        }

        $description = $this->translator->trans('booking.invoice.description', [
            '%date%' => $booking->getBusinessDay()->getDate()->format('d.m.Y'),
            '%room%' => $booking->getRoom()->getName(),
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

    private function writeAmount(int $amount): void
    {
        $amount /= 100;

        $this->writeValue(180, 145, 30, 8, $amount . ',00 €');
    }

    private function writeTotalAmount(int $amount): void
    {
        $amount /= 100;

        $this->writeValue(180, 182, 30, 8, $amount . ',00 €');
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
            $paymentMethodMessage = $this->translator->trans('booking.invoice.paid_by_voucher', [
                '%voucherCode%' => $payment->getVoucher()->getCode(),
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

        $dueMessage = $this->translator->trans('booking.invoice.due');
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
