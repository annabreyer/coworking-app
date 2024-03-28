<?php

declare(strict_types=1);

namespace App\Service;

use Yasumi\Provider\Germany\RhinelandPalatinate;
use Yasumi\ProviderInterface;
use Yasumi\Yasumi;

class PublicHolidayService
{
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

    private ProviderInterface $germanPublicHolidays;

    public function __construct()
    {
        $this->germanPublicHolidays = Yasumi::create(RhinelandPalatinate::class, (int) date('Y'), 'de_DE');
    }

    public function getGermanPublicHolidays(): ProviderInterface
    {
        return $this->germanPublicHolidays;
    }

    public function isGermanPublicHoliday(\DateTimeInterface $date): bool
    {
        return $this->germanPublicHolidays->isHoliday($date);
    }

    public function getGermanPublicHolidaysNamesForDate(\DateTimeInterface $date): string
    {
        $holidaysOnThatDay = $this->germanPublicHolidays->on($date);

        if (0 === $holidaysOnThatDay->count()) {
            return '';
        }
        $holidays = [];

        foreach ($holidaysOnThatDay as $holiday) {
            $holidays[] = $holiday->getName();
        }

        return implode(', ', $holidays);
    }
}
