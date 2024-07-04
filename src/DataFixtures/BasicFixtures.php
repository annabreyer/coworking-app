<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BusinessDay;
use App\Entity\Room;
use App\Entity\TermsOfUse;
use App\Entity\User;
use App\Entity\UserTermsOfUse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BasicFixtures extends Fixture
{
    public const ROOM_FOR_BOOKINGS = 'Room 3';
    public const FULLY_BOOKED_ROOM = 'Room 1';

    protected Generator $faker;

    public function __construct(private UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('de_DE');
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadTermsOfUse($manager);
        $this->loadUsers($manager);
        $this->loadBusinessDays($manager);
        $this->loadRooms($manager);
        $manager->flush();
    }

    private function loadUsers(ObjectManager $manager): void
    {
        $birthDate = new \DateTime('1978-06-30');
        $otherDate = new \DateTime('last week');

        $user = new User();
        $user->setEmail('user.one@annabreyer.dev');
        $user->setMobilePhone($this->faker->mobileNumber());
        $user->setFirstName($this->faker->firstName());
        $user->setLastName($this->faker->lastName());
        $user->setBirthdate($birthDate);
        $user->setAcceptedDataProtection($otherDate);
        $user->setAcceptedCodeOfConduct($otherDate);
        $password = $this->hasher->hashPassword($user, 'Passw0rd');
        $user->setPassword($password);

        $manager->persist($user);
        $manager->flush();

        $this->addReference('user1', $user);

        $userTermsOfUse = new UserTermsOfUse($user, $this->getReference('terms-of-use-1.0', TermsOfUse::class));
        $userTermsOfUse->setAcceptedOn($otherDate);
        $manager->persist($userTermsOfUse);
        $manager->flush();

        $birthDate = new \DateTime('1978-07-30');

        $user = new User();
        $user->setEmail('just.registered@annabreyer.dev');
        $user->setMobilePhone($this->faker->mobileNumber());
        $user->setFirstName($this->faker->firstName());
        $user->setLastName($this->faker->lastName());
        $user->setBirthdate($birthDate);

        $manager->persist($user);
        $manager->flush();

        $this->addReference('justRegisteredUser', $user);

        $birthDate = new \DateTime('1981-05-12');

        $user = new User();
        $user->setEmail('admin@annabreyer.dev');
        $user->setMobilePhone($this->faker->mobileNumber());
        $user->setFirstName('Anna');
        $user->setLastName('Breyer');
        $user->setBirthdate($birthDate);
        $password = $this->hasher->hashPassword($user, 'Passw0rd');
        $user->setPassword($password);
        $user->setRoles(['ROLE_SUPER_ADMIN']);

        $manager->persist($user);
        $manager->flush();

        $this->addReference('admin', $user);
    }

    private function loadTermsOfUse(ObjectManager $manager): void
    {
        $termsOfUse = new TermsOfUse();
        $termsOfUse->setVersion('1.0');
        $termsOfUse->setDate(new \DateTime('2024-06-30'));
        $termsOfUse->setPath('terms-of-use-1.0.md');
        $termsOfUse->setCreatedAt(new \DateTime('2021-06-30'));

        $manager->persist($termsOfUse);
        $manager->flush();
        $this->addReference('terms-of-use-1.0', $termsOfUse);
    }

    private function loadBusinessDays(ObjectManager $manager): void
    {
        $startDate = new \DateTime('2024-01-01');
        $endDate   = new \DateTime('2024-06-01');
        $interval  = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($startDate, $interval, $endDate);

        foreach ($dateRange as $date) {
            $businessDay = new BusinessDay($date);

            $manager->persist($businessDay);
            $this->addReference('businessDay-' . $date->format('Y-m-d'), $businessDay);

            if ($date->format('N') > 5) {
                $businessDay->setIsOpen(false);
            }
        }

        $manager->flush();
    }

    public function loadRooms(ObjectManager $manager)
    {
        $room = new Room();
        $room->setName(self::FULLY_BOOKED_ROOM);
        $room->setCapacity(6);

        $manager->persist($room);

        $room2 = new Room();
        $room2->setName('Room 2');
        $room2->setCapacity(0);

        $manager->persist($room2);

        $room3 = new Room();
        $room3->setName(self::ROOM_FOR_BOOKINGS);
        $room3->setCapacity(2);

        $manager->persist($room3);
        $manager->flush();

        $this->addReference('room1', $room);
        $this->addReference('room3', $room3);
    }
}
