<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Room;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CoworkingRoomFixtures extends Fixture implements DependentFixtureInterface
{
    public const ROOM_NAME      = 'Coworking-Raum';
    public const ROOM_CAPACITY  = 4;
    public const ROOM_REFERENCE = 'coworkingRoom';

    public function getDependencies(): array
    {
        return [
            BasicFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->getReference('room1', Room::class)->setIsOpen(false);
        $this->getReference('room3', Room::class)->setIsOpen(false);

        $room = new Room();
        $room->setName(self::ROOM_NAME);
        $room->setCapacity(self::ROOM_CAPACITY);

        $manager->persist($room);
        $manager->flush();

        $this->addReference(self::ROOM_REFERENCE, $room);
    }
}
