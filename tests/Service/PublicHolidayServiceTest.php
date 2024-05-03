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

        self::assertIsArray($publicHolidays);
        self::assertNotEmpty($publicHolidays);

        self::assertArrayHasKey(PublicHolidayService::NEW_YEARS_DAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::GOOD_FRIDAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::EASTER_MONDAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::INTL_WORKERS_DAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::ASCENSION, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::PENTECOST, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::PENTECOST_MONDAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::GERMAN_UNITY_DAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::CHRISTMAS_DAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::SECOND_CHRISTMAS_DAY, $publicHolidays);
        self::assertArrayHasKey(PublicHolidayService::NEW_YEARS_EVE, $publicHolidays);
    }
}
