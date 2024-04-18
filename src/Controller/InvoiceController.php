<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    #[Route('/invoice/{uuid}/download', name: 'invoice_download')]
    public function downloadInvoice(string $uuid, InvoiceGenerator $invoiceGenerator, Filesystem $filesystem, InvoiceRepository $invoiceRepository): BinaryFileResponse
    {
        try {
            $invoice = $invoiceRepository->findOneBy(['uuid' => $uuid]);
        } catch (\Exception $exception) {
            $invoice = null;
        }

        if (null === $invoice) {
            throw $this->createNotFoundException('Invoice not found.');
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
