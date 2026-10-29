<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingCalendarService
{
    use ClockAwareTrait;

    private const LINE_LENGTH = 75;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    public function generateCalendar(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Coworking//Buchungen//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Coworking Buchungen',
        ];

        $timestamp = $this->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');

        foreach ($this->bookingRepository->findBookingsForCalendar() as $booking) {
            $lines = [...$lines, ...$this->getEventLines($booking, $timestamp)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->foldLine(...), $lines)) . "\r\n";
    }

    /**
     * @return string[]
     */
    private function getEventLines(Booking $booking, string $timestamp): array
    {
        $businessDayDate = $booking->getBusinessDay()?->getDate();
        if (null === $businessDayDate) {
            return [];
        }

        $date     = \DateTimeImmutable::createFromInterface($businessDayDate);
        $roomName = (string) $booking->getRoom()?->getName();
        $typeName = (string) $booking->getType()?->getName();

        return [
            'BEGIN:VEVENT',
            'UID:' . $booking->getUuid() . '@coworking',
            'DTSTAMP:' . $timestamp,
            'DTSTART;VALUE=DATE:' . $date->format('Ymd'),
            'DTEND;VALUE=DATE:' . $date->modify('+1 day')->format('Ymd'),
            'SUMMARY:' . $this->escape($booking->getUser()?->getFullName() . ' (' . $typeName . ')'),
            'LOCATION:' . $this->escape($roomName),
            'DESCRIPTION:' . $this->escape('Raum: ' . $roomName . "\n" . 'Buchungsart: ' . $typeName),
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ];
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    private function foldLine(string $line): string
    {
        $parts = [];

        while (\strlen($line) > self::LINE_LENGTH) {
            $part    = mb_strcut($line, 0, self::LINE_LENGTH, 'UTF-8');
            $parts[] = $part;
            $line    = ' ' . substr($line, \strlen($part));
        }

        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
