<?php declare(strict_types = 1);

namespace App\Tests\Manager;

use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Manager\RoomCapacityChecker;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\Collection;

class RoomCapacityCheckerTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideRoomCapacityData
     */
    public function testHasSufficientBaseCapacity(int $capacity, bool $expected): void
    {
        $room = $this->createMock(Room::class);
        $room->method('getCapacity')->willReturn($capacity);

        $checker = new RoomCapacityChecker($room);
        $result = $checker->hasSufficientBaseCapacity();
        $this->assertSame($expected, $result);
    }

    public function provideRoomCapacityData(): array
    {
        return [
            'Insufficient capacity with zero' => [0, false],
            'Sufficient capacity with one' => [1, true],
            'Sufficient capacity with large number' => [100, true],
            'Insufficient capacity with negative number' => [-5, false],
        ];
    }

    /**
     * @test
     * @dataProvider provideRoomCapacityLeftData
     */
    public function testHasSufficientCapacityLeft(int $roomCapacity, int $bookingsCount, bool $expected): void
    {
        // Arrange
        $room = $this->createMock(Room::class);
        $room->method('getCapacity')->willReturn($roomCapacity);

        $bookingsCollection = $this->createMock(Collection::class);
        $bookingsCollection->method('count')->willReturn($bookingsCount);

        $businessDay = $this->createMock(BusinessDay::class);
        $businessDay->method('getBookingsForRoom')
                    ->with($room)
                    ->willReturn($bookingsCollection);

        $checker = new RoomCapacityChecker($room);
        $result = $checker->hasSufficientCapacityLeft($businessDay);
        $this->assertSame($expected, $result);
    }

    public function provideRoomCapacityLeftData(): array
    {
        return [
            'Sufficient capacity with more capacity than bookings' => [10, 5, true],
            'Sufficient capacity with capacity one greater than bookings' => [5, 4, true],
            'Insufficient capacity with equal capacity and bookings' => [5, 5, false],
            'Insufficient capacity with more bookings than capacity' => [5, 10, false],
            'Edge case with zero capacity' => [0, 0, false],
        ];
    }

    /**
     * Test that the correct method on BusinessDay is called with the right room
     */
    public function testBusinessDayIsQueriedWithCorrectRoom(): void
    {
        $room = $this->createMock(Room::class);
        $room->method('getCapacity')->willReturn(10);

        $bookingsCollection = $this->createMock(Collection::class);
        $bookingsCollection->method('count')->willReturn(5);

        $businessDay = $this->createMock(BusinessDay::class);
        $businessDay->expects($this->once())
                    ->method('getBookingsForRoom')
                    ->with($this->identicalTo($room))
                    ->willReturn($bookingsCollection);

        $checker = new RoomCapacityChecker($room);

        $checker->hasSufficientCapacityLeft($businessDay);
    }
}
