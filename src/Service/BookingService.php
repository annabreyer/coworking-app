<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;

class BookingService
{
    public function __construct(
        private RoomRepository $roomRepository,
        private BookingRepository $bookingRepository
    ) {
    }

    /**
     * @return array<mixed, mixed>
     *
     * @throws \Exception
     */
    public function generateAvailableBookingOptionsForDay(BusinessDay $businessDay, bool $includeWorkstations): array
    {
        $rooms = $this->roomRepository->findAllOpen();

        if (0 === \count($rooms)) {
            throw new \Exception('No rooms available');
        }

        $bookingOptions = [];

        foreach ($rooms as $room) {
            $availableCapacity            = $this->getAvailableRoomCapacityOnBusinessDay($room, $businessDay);
            $bookingOption['isAvailable'] = 0 === $availableCapacity ? false : $businessDay->isOpen();
            $bookingOption['roomId']      = $room->getId();
            $bookingOption['roomName']    = $room->getName();

            if ($includeWorkstations) {
                $bookingOption['workStations'] = $this->getWorkStations($room);
            }

            $bookingOptions[] = $bookingOption;
        }

        return $bookingOptions;
    }

    private function getAvailableRoomCapacityOnBusinessDay(Room $room, BusinessDay $businessDay): int
    {
        if (null === $room->getId()) {
            throw new \Exception('Can not get available Room Capacity on Business Day. Room ID is null');
        }

        if (null === $businessDay->getDate()) {
            throw new \Exception('Can not get available Room Capacity on Business Day. BusinessDay date is null');
        }

        $bookingCount = $this->bookingRepository->countBookingsForRoomOnDay($room->getId(), $businessDay->getDate());

        return $room->getCapacity() - $bookingCount;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function getWorkStations(Room $room): array
    {
        // @todo: make this method

        return [];
    }
}
