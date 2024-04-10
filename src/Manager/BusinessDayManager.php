<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\BusinessDay;
use App\Repository\BusinessDayRepository;
use App\Service\PublicHolidayService;
use Doctrine\ORM\EntityManagerInterface;

class BusinessDayManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessDayRepository $businessDayRepository,
        private PublicHolidayService $publicHolidayService
    ) {
    }

    public function generateBusinessDaysUntil(\DateTimeInterface $endDate)
    {
        $startDate = $this->businessDayRepository->findLastBusinessDay();
        $startDate = $startDate ? $startDate->getDate() : new \DateTime();

        $interval  = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

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

    public function createBusinessDay(\DateTimeInterface $date, bool $flush = true)
    {
        $businessDay = new BusinessDay();
        $businessDay->setDate($date);

        $this->entityManager->persist($businessDay);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $businessDay;
    }
}
