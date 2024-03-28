<?php declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Room;
use App\Entity\WorkStation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class WorkstationFixtures extends Fixture
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('de_DE');
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadRoom($manager);
        $this->loadWorkstations($manager);

        $manager->flush();
    }

    public function loadRoom(ObjectManager $manager)
    {
        $room = new Room();
        $room->setName('Room 1');
        $room->setCapacity(10);

        $manager->persist($room);
        $manager->flush();

        $this->addReference('room1', $room);
    }

    public function loadWorkstations(ObjectManager $manager)
    {
        $room = $this->getreference('room1', Room::class);

        $workStation1 = new WorkStation();
        $workStation1->setName('4er Tisch, Platz 1');
        $workStation1->setRoom($room);
        $manager->persist($workStation1);
        $this->addReference('workStation1', $workStation1);

        $workStation2 = new WorkStation();
        $workStation2->setName('4er Tisch, Platz 2');
        $workStation2->setRoom($room);
        $manager->persist($workStation2);
        $this->addReference('workStation2', $workStation2);

        $workStation3 = new WorkStation();
        $workStation3->setName('4er Tisch, Platz 3');
        $workStation3->setRoom($room);
        $manager->persist($workStation3);
        $this->addReference('workStation3', $workStation3);

        $workStation4 = new WorkStation();
        $workStation4->setName('4er Tisch, Platz 4');
        $workStation4->setRoom($room);
        $manager->persist($workStation4);
        $this->addReference('workStation4', $workStation4);

        $manager->flush();
    }

}