<?php declare(strict_types = 1);

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\WorkStation;
use PHPUnit\Framework\TestCase;

class WorkstationTest extends TestCase
{
    public function testIsAvailableReturnsFalseWhenThereIsAlreadyABookingForThatDay()
    {
        $businessDay = new BusinessDay();
        $businessDay->setDate(new \DateTimeImmutable('2024-02-06'));
        $booking = new Booking();
        $booking->setBusinessDay($businessDay);
        $workStation = new WorkStation();
        $workStation->setName('Workstation 1');
        $workStation->addBooking($booking);

        $this->assertSame($workStation, $booking->getWorkStation());
        $this->assertFalse($workStation->isAvailableOn($businessDay));
    }

}