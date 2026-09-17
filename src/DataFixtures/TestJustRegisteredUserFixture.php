<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;
use Faker\Generator;

/**
 * Loads a single user who has just registered (no accepted terms).
 * For use in registration-related tests.
 */
class TestJustRegisteredUserFixture extends Fixture
{
    public const EMAIL = 'just.registered@example.com';
    public const PASSWORD = 'Passw0rd';

    private Generator $faker;

    public function __construct(private readonly UserPasswordHasherInterface $hasher) {
        $this->faker = Factory::create('de_DE');
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail(self::EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $user->setMobilePhone($this->faker->mobileNumber());
        // No accepted data protection or code of conduct
        $manager->persist($user);
        $manager->flush();
        $this->addReference('justRegisteredUser', $user);
    }
}
