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
        private BookingRepository $bookingRepository,
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
            $bookingCount                  = $this->bookingRepository->countBookingsForRoomOnDay((int) $room->getId(), $businessDay->getDate());
            $availableCapacity             = $room->getCapacity() - $bookingCount;
            $bookingOption['isAvailable']  = 0 === $availableCapacity ? false : $businessDay->isOpen();
            $bookingOption['roomId']       = $room->getId();
            $bookingOption['roomName']     = $room->getName();
            $bookingOption['capacity']     = $room->getCapacity();
            $bookingOption['bookingCount'] = $bookingCount;

            if ($includeWorkstations) {
                $bookingOption['workStations'] = $this->getWorkStations($room);
            }

            $bookingOptions[] = $bookingOption;
        }

        return $bookingOptions;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws \Exception
     */
    public function generateBookingOptionsForRoomsWithCapacity(BusinessDay $businessDay): array
    {
        return array_values(array_filter(
            $this->generateAvailableBookingOptionsForDay($businessDay, false),
            static fn (array $option) => $option['capacity'] > 0
        ));
    }

    /**
     * @param BusinessDay[] $businessDays
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \Exception
     */
    public function generateDayOverview(array $businessDays): array
    {
        $dayOverview = [];

        foreach ($businessDays as $businessDay) {
            if (false === $businessDay->isOpen()) {
                continue;
            }

            $bookableOptions = $this->generateBookingOptionsForRoomsWithCapacity($businessDay);

            $capacity     = array_sum(array_column($bookableOptions, 'capacity'));
            $bookingCount = array_sum(array_column($bookableOptions, 'bookingCount'));

            $dayOverview[] = [
                'businessDay'  => $businessDay,
                'capacity'     => $capacity,
                'bookingCount' => $bookingCount,
                'isAvailable'  => \in_array(true, array_column($bookableOptions, 'isAvailable'), true),
                'singleRoomId' => 1 === \count($bookableOptions) ? $bookableOptions[0]['roomId'] : null,
            ];
        }

        return $dayOverview;
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
