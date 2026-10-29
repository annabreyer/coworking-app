<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Clock\ClockAwareTrait;
use Yasumi\Provider\Germany\RhinelandPalatinate;
use Yasumi\ProviderInterface;
use Yasumi\Yasumi;

class PublicHolidayService
{
    use ClockAwareTrait;

    public const NEW_YEARS_DAY        = 'newYearsDay';
    public const GOOD_FRIDAY          = 'goodFriday';
    public const EASTER_MONDAY        = 'easterMonday';
    public const INTL_WORKERS_DAY     = 'internationalWorkersDay';
    public const ASCENSION            = 'ascensionDay';
    public const PENTECOST            = 'pentecost';
    public const PENTECOST_MONDAY     = 'pentecostMonday';
    public const GERMAN_UNITY_DAY     = 'germanUnityDay';
    public const CHRISTMAS_DAY        = 'christmasDay';
    public const SECOND_CHRISTMAS_DAY = 'secondChristmasDay';
    public const NEW_YEARS_EVE        = 'newYearsEve';

    /** @var array<int, ProviderInterface> */
    private array $germanPublicHolidays = [];

    public function getGermanPublicHolidays(?int $year = null): ProviderInterface
    {
        $year ??= (int) $this->now()->format('Y');

        return $this->germanPublicHolidays[$year] ??= Yasumi::create(RhinelandPalatinate::class, $year, 'de_DE');
    }

    public function isGermanPublicHoliday(\DateTimeInterface $date): bool
    {
        return $this->getGermanPublicHolidaysForDate($date)->isHoliday($date);
    }

    public function getGermanPublicHolidaysNamesForDate(\DateTimeInterface $date): string
    {
        $holidaysOnThatDay = $this->getGermanPublicHolidaysForDate($date)->on($date);

        if (0 === $holidaysOnThatDay->count()) {
            return '';
        }
        $holidays = [];

        foreach ($holidaysOnThatDay as $holiday) {
            $holidays[] = $holiday->getName();
        }

        return implode(', ', $holidays);
    }

    private function getGermanPublicHolidaysForDate(\DateTimeInterface $date): ProviderInterface
    {
        return $this->getGermanPublicHolidays((int) $date->format('Y'));
    }
}
