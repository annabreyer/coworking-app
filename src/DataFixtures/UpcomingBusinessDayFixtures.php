<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BusinessDay;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class UpcomingBusinessDayFixtures extends Fixture implements DependentFixtureInterface
{
    use ClockAwareTrait;

    public const BOOKABLE_MONTHS           = 6;
    public const OPEN_DAY_REFERENCE_PREFIX = 'upcomingOpenDay-';

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $today     = $this->now()->setTime(0, 0, 0);
        $endDate   = $today->modify(\sprintf('+%d months', self::BOOKABLE_MONTHS));
        $dateRange = new \DatePeriod($today, new \DateInterval('P1D'), $endDate);
        $openDays  = 0;

        foreach ($dateRange as $date) {
            $businessDay = new BusinessDay(\DateTime::createFromImmutable($date));

            if ($date->format('N') > 5) {
                $businessDay->setIsOpen(false);
            } else {
                $this->addReference(self::OPEN_DAY_REFERENCE_PREFIX . $openDays, $businessDay);
                ++$openDays;
            }

            $manager->persist($businessDay);
        }

        $manager->flush();
    }
}
