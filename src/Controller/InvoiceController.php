<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    #[Route('/invoice/{id}/download', name: 'invoice_download')]
    public function downloadInvoice(Invoice $invoice, InvoiceGenerator $invoiceGenerator, Filesystem $filesystem): BinaryFileResponse
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to download this invoice');
        }

        $invoicePath = $invoiceGenerator->getTargetDirectory($invoice) . '/' . $invoice->getNumber() . '.pdf';

        if (false === $filesystem->exists($invoicePath)) {
            $isAlreadyPaid = $invoice->getIsPaid();
            $invoiceGenerator->generateBookingInvoice($invoice, $isAlreadyPaid);
        }

        return new BinaryFileResponse($invoicePath);
    }
}
