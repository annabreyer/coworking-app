<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractUserFixture extends Fixture
{
    protected Generator $faker;

    public const USER_EMAIL = 'user.one@example.org';
    public const ADMIN_EMAIL = 'admin@example.org';
    public const JUST_REGISTERED_EMAIL = 'just.registered@example.org';
    public const PASSWORD = 'Passw0rd';

    public function __construct(protected UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('de_DE');
    }

    protected function createUser(string $email, string $password, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setMobilePhone($this->faker->mobileNumber());
        if ($roles) {
            $user->setRoles($roles);
        }
        return $user;
    }
}
 