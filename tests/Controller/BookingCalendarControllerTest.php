<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BookingFixtures;
use App\Entity\Booking;
use App\Entity\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class BookingCalendarControllerTest extends BaseTestController
{
    private const FEED_URL = '/booking-calendar/test-calendar-token.ics';

    public function testFeedContainsOneEventPerBooking(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BookingFixtures::class]);

        $client->request('GET', self::FEED_URL);

        static::assertResponseIsSuccessful();
        static::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');

        $content  = (string) $client->getResponse()->getContent();
        $bookings = $this->getBookingRepository()->findAll();

        static::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $content);
        static::assertStringEndsWith("END:VCALENDAR\r\n", $content);
        static::assertSame(\count($bookings), substr_count($content, 'BEGIN:VEVENT'));

        $booking = $bookings[0];
        static::assertStringContainsString('UID:' . $booking->getUuid() . '@coworking', $content);
        static::assertStringContainsString(
            'SUMMARY:' . $booking->getUser()?->getFullName() . ' (' . BookingType::NAME_FULL_DAY . ')',
            $content
        );
        static::assertStringContainsString('LOCATION:' . $booking->getRoom()?->getName(), $content);
        static::assertStringContainsString("DTSTART;VALUE=DATE:20240401\r\nDTEND;VALUE=DATE:20240402", $content);
    }

    public function testFeedShowsTheBookingTypeOfHalfDays(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BookingFixtures::class]);

        $booking = $this->getBookingOn(BookingFixtures::BOOKING_TO_BE_CANCELLED_DATE);
        $booking->setType($this->getBookingType(BookingType::NAME_HALF_DAY));
        $this->getEntityManager()->flush();

        $client->request('GET', self::FEED_URL);

        static::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString(
            "DTSTART;VALUE=DATE:20240314\r\nDTEND;VALUE=DATE:20240315\r\nSUMMARY:"
            . $booking->getUser()?->getFullName() . ' (' . BookingType::NAME_HALF_DAY . ')',
            $content
        );
    }

    public function testFeedLeavesOutCancelledAndUnfinishedBookings(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BookingFixtures::class]);

        $cancelledBooking = $this->getBookingOn(BookingFixtures::BOOKING_TO_BE_CANCELLED_DATE);
        $cancelledBooking->setIsCancelled(true);
        $bookings          = $this->getBookingRepository()->findAll();
        $unfinishedBooking = $bookings[0] === $cancelledBooking ? $bookings[1] : $bookings[0];
        $unfinishedBooking->setAmount(null);
        $this->getEntityManager()->flush();

        $client->request('GET', self::FEED_URL);

        static::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertSame(\count($bookings) - 2, substr_count($content, 'BEGIN:VEVENT'));
        static::assertStringNotContainsString((string) $cancelledBooking->getUuid(), $content);
        static::assertStringNotContainsString((string) $unfinishedBooking->getUuid(), $content);
        static::assertStringNotContainsString('20240314', $content);
    }

    public function testFeedRejectsWrongToken(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BookingFixtures::class]);

        $client->request('GET', '/booking-calendar/wrong-token.ics');

        static::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testFeedRejectsMissingToken(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BookingFixtures::class]);

        $client->request('GET', '/booking-calendar/.ics');
        static::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('GET', '/booking-calendar/');
        static::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function getBookingOn(string $date): Booking
    {
        $booking = $this->getBookingRepository()->findOneBy(['businessDay' => $this->getBusinessDay($date)]);
        static::assertInstanceOf(Booking::class, $booking);

        return $booking;
    }

    private function getBookingRepository(): BookingRepository
    {
        $bookingRepository = static::getContainer()->get(BookingRepository::class);
        static::assertInstanceOf(BookingRepository::class, $bookingRepository);

        return $bookingRepository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
