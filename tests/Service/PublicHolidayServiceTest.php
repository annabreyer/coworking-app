<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicHolidayService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class PublicHolidayServiceTest extends TestCase
{
    use ClockSensitiveTrait;

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

    public function testGetPublicHolidaysDefaultsToCurrentYearOfClock(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-15'));
        $publicHolidayService = new PublicHolidayService();

        self::assertSame(2026, $publicHolidayService->getGermanPublicHolidays()->getYear());
    }

    public function testGetPublicHolidaysReturnsHolidaysOfGivenYear(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-15'));
        $publicHolidayService = new PublicHolidayService();

        self::assertSame(2027, $publicHolidayService->getGermanPublicHolidays(2027)->getYear());
    }

    public function testIsGermanPublicHolidayDetectsHolidaysOfFollowingYear(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-15'));
        $publicHolidayService = new PublicHolidayService();

        self::assertTrue($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2026-12-25')));
        self::assertTrue($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2027-01-01')));
        self::assertTrue($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2027-03-26')));
        self::assertTrue($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2027-03-29')));
        self::assertFalse($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2027-01-04')));
    }

    public function testIsGermanPublicHolidayDetectsHolidaysOfPreviousYear(): void
    {
        self::mockTime(new \DateTimeImmutable('2027-02-01'));
        $publicHolidayService = new PublicHolidayService();

        self::assertTrue($publicHolidayService->isGermanPublicHoliday(new \DateTimeImmutable('2026-12-26')));
    }

    public function testGetGermanPublicHolidaysNamesForDateReturnsNamesOfFollowingYear(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-15'));
        $publicHolidayService = new PublicHolidayService();

        self::assertSame('Neujahr', $publicHolidayService->getGermanPublicHolidaysNamesForDate(new \DateTimeImmutable('2027-01-01')));
        self::assertSame('', $publicHolidayService->getGermanPublicHolidaysNamesForDate(new \DateTimeImmutable('2027-01-04')));
    }
}
