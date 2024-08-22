<?php

declare(strict_types=1);

namespace App\Controller;

use App\Manager\InvoiceManager;
use App\Repository\InvoiceRepository;
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
        InvoiceManager $invoiceManager,
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

        $accessDenied = $invoice->getUser() !== $this->getUser();
        if ($accessDenied) {
            $accessDenied = false === $this->isGranted('ROLE_SUPER_ADMIN');
        }

        if ($accessDenied) {
            throw $this->createAccessDeniedException('You are not allowed to download this invoice');
        }

        if (null === $invoice->getFilePath() || false === $filesystem->exists($invoice->getFilePath())) {
            $invoiceManager->generateInvoicePdf($invoice);
        }

        $filePath = $invoice->getFilePath();

        if (null === $filePath) {
            throw $this->createNotFoundException('Invoice file not found');
        }

        return new BinaryFileResponse($filePath);
    }
}
