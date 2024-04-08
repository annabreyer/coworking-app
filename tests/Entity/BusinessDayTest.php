<?php declare(strict_types = 1);

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Room;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BusinessDayTest extends KernelTestCase
{
    public function testGetBookingsForRoomReturnsCollection(): void
    {
        $room        = new Room();
        $businessDay = new BusinessDay();
        $booking     = new Booking();
        $booking->setRoom($room);
        $businessDay->addBooking($booking);

        $this->assertIsIterable($businessDay->getBookingsForRoom($room));
    }

    public function testGetBookingsForRoomReturnsCorrectRoom(): void
    {
        $room        = new Room();
        $businessDay = new BusinessDay();
        $booking     = new Booking();
        $booking->setRoom($room);
        $businessDay->addBooking($booking);

        $this->assertCount(1, $businessDay->getBookingsForRoom($room));
        $this->assertSame($booking, $businessDay->getBookingsForRoom($room)[0]);
    }
}
