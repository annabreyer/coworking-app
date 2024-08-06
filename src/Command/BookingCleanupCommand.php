<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:booking_cleanup', description: 'Removes unfinished bookings (bookings without invoice) that are older than 1 day')]
class BookingCleanupCommand extends Command
{
    public function __construct(private readonly BookingRepository $bookingRepository, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $unfinishedBookings = $this->bookingRepository->findUnfinishedBookingsSince(new \DateTimeImmutable('-1 day'));

        foreach ($unfinishedBookings as $booking) {
            if (null !== $booking->getInvoice()) {
                continue;
            }

            $this->entityManager->remove($booking);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
