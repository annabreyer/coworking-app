<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/invoice/{uuid}/download', name: 'invoice_download')]
    public function downloadInvoice(
        string $uuid,
        InvoiceGenerator $invoiceGenerator,
        Filesystem $filesystem,
        InvoiceRepository $invoiceRepository,
    ): BinaryFileResponse|RedirectResponse {
        try {
            $invoice = $invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $this->logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);

            $invoice = null;
        }

        if (null === $invoice) {
            $this->addFlash('error', $this->translator->trans('invoice_download.not_found', [], 'flash'));

            return $this->redirectToRoute('home');
        }

        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to download this invoice');
        }

        $invoicePath = $invoiceGenerator->getTargetDirectory($invoice) . '/' . $invoice->getNumber() . '.pdf';

        if (false === $filesystem->exists($invoicePath)) {
            $invoiceGenerator->generateBookingInvoice($invoice);
        }

        return new BinaryFileResponse($invoicePath);
    }
}
