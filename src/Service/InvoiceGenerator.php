<?php

declare(strict_types=1);

namespace App\Service;

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

        $context = $this->buildBaseContext($invoice, $user);

        $context['items'][] = $this->buildBookingItem($invoiceBooking);

        $paymentSpecifics          = $this->buildBookingPaymentSpecifics($invoice);
        $context['items']          = array_merge($context['items'], $paymentSpecifics['extraItems']);
        $context['showTotal']      = true;
        $context['totalAmount']    = $this->formatAmount($paymentSpecifics['totalAmount']);
        $context['paymentMessage'] = $paymentSpecifics['paymentMessage'];

        $this->render($invoice, $context);
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

        $context = $this->buildBaseContext($invoice, $user);

        $context['items'][] = [
            'position'    => 1,
            'description' => $this->translator->trans('invoice.description.voucher', [
                '%name%'           => $voucherType->getName(),
                '%validityMonths%' => $voucherType->getValidityMonths(),
            ], 'invoice'),
            'codes'  => $this->getVoucherCodes($invoice),
            'amount' => $this->formatAmount($invoiceAmount / 100),
        ];

        $context['showTotal']   = false;
        $context['totalAmount'] = $this->formatAmount($invoiceAmount / 100);

        if ($invoice->isFullyPaidByPayPal()) {
            $context['showTotal']      = true;
            $context['paymentMessage'] = $this->getAlreadyPaidMessage($invoice);
        }

        if (false === $invoice->isFullyPaid()) {
            $context['showTotal']      = true;
            $context['paymentMessage'] = $this->getDueMessage($invoice);
        }

        $this->render($invoice, $context);
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

        $context = $this->buildBaseContext($invoice, $user);

        $context['items'][] = [
            'position'    => 1,
            'description' => (string) $invoice->getDescription(),
            'codes'       => null,
            'amount'      => $this->formatAmount($invoiceAmount / 100),
        ];

        $context['showTotal']      = true;
        $context['totalAmount']    = $this->formatAmount($invoiceAmount / 100);
        $context['paymentMessage'] = $this->getDueMessage($invoice);

        $this->render($invoice, $context);
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

    /**
     * @return array<string, mixed>
     */
    private function buildBaseContext(Invoice $invoice, User $user): array
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

        $context = [
            'headerLogo'    => $this->getAssetDataUri('header_logo.png'),
            'vielenDank'    => $this->getAssetDataUri('vielen_dank.png'),
            'invoiceNumber' => $invoice->getNumber(),
            'invoiceDate'   => $invoice->getDate()->format('d.m.Y'),
            'clientNumber'  => $this->invoiceClientNumberPrefix . InvoiceManager::getClientNumber($user->getId()),
            'clientName'    => $user->getFullName(),
            'invoiceType'   => $this->translator->trans(
                $invoice->isRefund() ? 'invoice.type.refund' : 'invoice.type.invoice',
                [],
                'invoice'
            ),
            'introText' => $this->translator->trans(
                $invoice->isRefund() ? 'invoice.intro.refund' : 'invoice.intro.invoice',
                [],
                'invoice'
            ),
            'items'          => [],
            'showTotal'      => false,
            'totalAmount'    => null,
            'paymentMessage' => null,
        ];

        if ($user->hasAddress()) {
            $context['clientStreet']          = $user->getStreet();
            $context['clientPostCodeAndCity'] = $user->getPostCode() . ' ' . $user->getCity();
            $context['clientEmail']           = null;
        } else {
            $context['clientStreet']          = null;
            $context['clientPostCodeAndCity'] = null;
            $context['clientEmail']           = $user->getEmail() ?? '';
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBookingItem(Booking $booking): array
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

        return [
            'position'    => 1,
            'description' => $this->translator->trans('invoice.description.booking', [
                '%date%' => $bookingDate->format('d.m.Y'),
                '%room%' => $bookingRoom->getName(),
            ], 'invoice'),
            'codes'  => null,
            'amount' => $this->formatAmount($bookingAmount / 100),
        ];
    }

    /**
     * @return array{extraItems: array<int, array<string, mixed>>, totalAmount: float, paymentMessage: string|null}
     */
    private function buildBookingPaymentSpecifics(Invoice $invoice): array
    {
        if ($invoice->isFullyPaidByVoucher()) {
            return [
                'extraItems'     => $this->buildVoucherPaymentItems($invoice),
                'totalAmount'    => 0.0,
                'paymentMessage' => null,
            ];
        }

        if ($invoice->isFullyPaidByPayPal()) {
            return [
                'extraItems'     => [],
                'totalAmount'    => $invoice->getAmount() / 100,
                'paymentMessage' => $this->getAlreadyPaidMessage($invoice),
            ];
        }

        return [
            'extraItems'     => [],
            'totalAmount'    => $invoice->getAmount() / 100,
            'paymentMessage' => $this->getDueMessage($invoice),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
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

            $items[] = [
                'position'    => $position,
                'description' => $this->translator->trans('invoice.payment.voucher', [
                    '%voucherCode%' => $paymentVoucher->getCode(),
                ], 'invoice'),
                'codes'  => null,
                'amount' => '-' . $this->formatAmount($payment->getAmount() / 100),
            ];
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

    /**
     * @param array<string, mixed> $context
     */
    private function render(Invoice $invoice, array $context): void
    {
        $html = $this->twig->render('invoice/pdf/invoice.html.twig', $context);

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
