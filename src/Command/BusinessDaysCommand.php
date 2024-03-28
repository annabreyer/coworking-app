<?php

namespace App\Command;

use App\Manager\BusinessDayManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'business-days',
    description: 'Add a short description for your command',
)]
class BusinessDaysCommand extends Command
{
    public function __construct(private BusinessDayManager $businessDaysManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('endDate', InputArgument::OPTIONAL, 'Format Ymd')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $endDate = $input->getArgument('endDate');

        if ($endDate) {
            $io->note(sprintf('You passed an argument: %s', $endDate));
            try {
                $date = \DateTimeImmutable::createFromFormat('Ymd', $endDate);
            } catch (\Exception $exception) {
                $io->error('Invalid date format. Please use Ymd');

                return Command::FAILURE;
            }
        } else {
            $today = new \DateTimeImmutable();
            $date = $today->modify('+6 months');
        }

        $io->note(sprintf('BusinessDays will be generated until: %s', $date->format('Ymd')));
        $this->businessDaysManager->generateBusinessDaysUntil($date);

        $io->success('Business Days have been generated!');

        return Command::SUCCESS;
    }
}
