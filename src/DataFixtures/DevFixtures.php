<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Room;
use App\Entity\User;
use App\Manager\BusinessDayManager;
use App\Repository\BusinessDayRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class DevFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use ClockAwareTrait;

    public const BOOKABLE_MONTHS        = 6;
    public const ROOM_NAME              = 'Coworking-Raum';
    public const ROOM_CAPACITY          = 4;
    public const ONE_BOOKING_DAY_INDEX  = 1;
    public const FULLY_BOOKED_DAY_INDEX = 3;

    public function __construct(
        private readonly BusinessDayManager $businessDayManager,
        private readonly BusinessDayRepository $businessDayRepository,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
            PriceFixtures::class,
            VoucherFixtures::class,
            InvoiceFixtures::class,
            BookingFixtures::class,
            BookingWithInvoiceNoPaymentFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $room = $this->loadRoom($manager);
        $this->businessDayManager->generateBusinessDaysUntil($this->now()->modify(\sprintf('+%d months', self::BOOKABLE_MONTHS)));
        $this->loadUpcomingBookings($manager, $room);
    }

    private function loadRoom(ObjectManager $manager): Room
    {
        $this->getReference('room1', Room::class)->setIsOpen(false);
        $this->getReference('room3', Room::class)->setIsOpen(false);

        $room = new Room();
        $room->setName(self::ROOM_NAME);
        $room->setCapacity(self::ROOM_CAPACITY);

        $manager->persist($room);
        $manager->flush();

        return $room;
    }

    private function loadUpcomingBookings(ObjectManager $manager, Room $room): void
    {
        $openDays = array_values(array_filter(
            $this->businessDayRepository->findBusinessDaysStartingWithDate($this->now()->setTime(0, 0, 0)),
            static fn ($businessDay) => $businessDay->isOpen()
        ));

        $bookingsPerDay = [
            self::ONE_BOOKING_DAY_INDEX  => ['user2'],
            self::FULLY_BOOKED_DAY_INDEX => ['user1', 'user2', 'admin', 'firstBookingUser'],
        ];

        foreach ($bookingsPerDay as $dayIndex => $userReferences) {
            if (false === isset($openDays[$dayIndex])) {
                continue;
            }

            foreach ($userReferences as $userReference) {
                $booking = new Booking();
                $booking->setBusinessDay($openDays[$dayIndex]);
                $booking->setRoom($room);
                $booking->setUser($this->getReference($userReference, User::class));
                $booking->setAmount(PriceFixtures::SINGLE_PRICE_AMOUNT);

                $manager->persist($booking);
            }
        }

        $manager->flush();
    }
}
