<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\InvoiceRepository;
use App\Service\InvoiceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:add_invoice_file_path', description: 'Adds invoice path to invoices without it.')]
class AddInvoiceFilePathCommand extends Command
{
    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invoices = $this->invoiceRepository->findBy(['filePath' => null]);

        foreach ($invoices as $invoice) {
            $invoice->setFilePath($this->invoiceGenerator->getTargetDirectory($invoice) . '/' . $invoice->getNumber() . '.pdf');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
