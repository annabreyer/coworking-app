<?php declare(strict_types = 1);

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

    public function generateAvailableBookingOptionsForDay(BusinessDay $businessDay, bool $includeWorkstations): array
    {
        $rooms = $this->roomRepository->findAllOpen();

        if (0 === count($rooms)) {
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
        $bookingCount = $this->bookingRepository->countBookingsForRoomOnDay($room->getId(), $businessDay->getDate());

        return $room->getCapacity() - $bookingCount;
    }

    //@todo: make this method
    private function getWorkStations(Room $room): array
    {
        $workStations          = $room->getWorkStations();
        $availableWorkStations = [];
        foreach ($workStations as $workStation) {
            if ($workStation->isOpen()) {
                $availableWorkStations[] = $workStation;
            }
        }

        return $availableWorkStations;
    }

}