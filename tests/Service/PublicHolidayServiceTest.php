<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicHolidayService;
use PHPUnit\Framework\TestCase;

class PublicHolidayServiceTest extends TestCase
{
    public function testGetPublicHolidaysReturnsPublicHolidays(): void
    {
        $publicHolidayService = new PublicHolidayService();
        $publicHolidays       = $publicHolidayService->getGermanPublicHolidays()->getHolidays();

        static::assertIsArray($publicHolidays);
        static::assertNotEmpty($publicHolidays);

        static::assertArrayHasKey(PublicHolidayService::NEW_YEARS_DAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::GOOD_FRIDAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::EASTER_MONDAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::INTL_WORKERS_DAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::ASCENSION, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::PENTECOST, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::PENTECOST_MONDAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::GERMAN_UNITY_DAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::CHRISTMAS_DAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::SECOND_CHRISTMAS_DAY, $publicHolidays);
        static::assertArrayHasKey(PublicHolidayService::NEW_YEARS_EVE, $publicHolidays);
    }
}
