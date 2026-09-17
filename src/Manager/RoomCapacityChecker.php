<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\BusinessDay;
use App\Entity\Room;

class RoomCapacityChecker
{
    public function __construct(private readonly Room $room)
    {
    }

    public function hasSufficientBaseCapacity(): bool
    {
        return $this->room->getCapacity() > 0;
    }

    public function hasSufficientCapacityLeft(BusinessDay $businessDay): bool
    {
        return $businessDay->getBookingsForRoom($this->room)->count() < $this->room->getCapacity();
    }
}
