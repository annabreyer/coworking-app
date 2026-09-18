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
 * Loads a regular user and an admin user for login and user action tests.
 */
class TestUserAndAdminFixture extends Fixture
{
    public const USER_EMAIL = 'user.one@annabreyer.dev';
    public const ADMIN_EMAIL = 'admin@annabreyer.dev';
    public const PASSWORD = 'Passw0rd';

    private Generator $faker;

    public function __construct(private readonly UserPasswordHasherInterface $hasher) {
        $this->faker = Factory::create('de_DE');
    }

    public function load(ObjectManager $manager): void
    {
        // Regular user
        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $user->setMobilePhone($this->faker->mobileNumber());
        $manager->persist($user);
        $this->addReference('user.one', $user);

        // Admin user
        $admin = new User();
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setPassword($this->hasher->hashPassword($admin, self::PASSWORD));
        $admin->setMobilePhone($this->faker->mobileNumber());
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $manager->persist($admin);
        $this->addReference('admin', $admin);

        $manager->flush();
    }
} 