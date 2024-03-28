<?php declare(strict_types = 1);

namespace App\Tests\Service;

use App\Service\PublicHolidayService;
use PHPUnit\Framework\TestCase;

class PublicHolidayServiceTest extends TestCase
{
    public function testGetPublicHolidaysReturnsPublicHolidays(): void
    {
        $publicHolidayService = new PublicHolidayService();
        $publicHolidays = $publicHolidayService->getGermanPublicHolidays()->getHolidays();

        $this->assertIsArray($publicHolidays);
        $this->assertNotEmpty($publicHolidays);

        $this->assertArrayHasKey(PublicHolidayService::NEW_YEARS_DAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::GOOD_FRIDAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::EASTER_MONDAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::INTL_WORKERS_DAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::ASCENSION, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::PENTECOST, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::PENTECOST_MONDAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::GERMAN_UNITY_DAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::CHRISTMAS_DAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::SECOND_CHRISTMAS_DAY, $publicHolidays);
        $this->assertArrayHasKey(PublicHolidayService::NEW_YEARS_EVE, $publicHolidays);
    }
}
