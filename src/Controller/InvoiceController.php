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

class InvoiceController extends AbstractController
{
    #[Route('/invoice/{uuid}/download', name: 'invoice_download')]
    public function downloadInvoice(
        string $uuid,
        InvoiceGenerator $invoiceGenerator,
        Filesystem $filesystem,
        InvoiceRepository $invoiceRepository,
        LoggerInterface $logger
    ): BinaryFileResponse|RedirectResponse
    {
        try {
            $invoice = $invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $logger->error('Invoice not found. ' . $exception->getMessage(), ['uuid' => $uuid]);

            $invoice = null;
        }

        if (null === $invoice) {
            $this->addFlash('error', 'Invoice not found.');

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
