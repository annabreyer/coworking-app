<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\BusinessDay;
use App\Repository\BusinessDayRepository;
use App\Service\PublicHolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class BusinessDayManager
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessDayRepository $businessDayRepository,
        private PublicHolidayService $publicHolidayService,
    ) {
    }

    public function generateBusinessDaysUntil(\DateTimeInterface $endDate): void
    {
        $dateRange = $this->getDateRange($endDate);

        foreach ($dateRange as $date) {
            if ($this->businessDayRepository->findOneBy(['date' => $date])) {
                continue;
            }

            $businessDay = $this->createBusinessDay($date, false);

            if (
                $this->publicHolidayService->isGermanPublicHoliday($date)
                || $date->format('N') > 5
            ) {
                $businessDay->setIsOpen(false);
            }
        }

        $this->entityManager->flush();
    }

    public function createBusinessDay(\DateTimeInterface $date, bool $flush = true): BusinessDay
    {
        $businessDay = new BusinessDay($date);

        $this->entityManager->persist($businessDay);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $businessDay;
    }

    private function getDateRange(\DateTimeInterface $endDate): \DatePeriod
    {
        $lastBusinessDay = $this->businessDayRepository->findLastBusinessDay();
        $endDate         = new \DateTimeImmutable($endDate->format('Y-m-d') . '+ 1 day');
        $interval        = new \DateInterval('P1D');

        if (null === $lastBusinessDay) {
            return new \DatePeriod($this->now(), $interval, $endDate);
        }

        return new \DatePeriod($lastBusinessDay->getDate(), $interval, $endDate);
    }
}
