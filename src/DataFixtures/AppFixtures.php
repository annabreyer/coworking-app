<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TermsOfUse;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    protected Generator $faker;

    public function __construct(private UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('de_DE');
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadTermsOfUse($manager);
        $this->loadUser($manager);

        $manager->flush();
    }

    private function loadUser(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('user.one@annabreyer.dev');

        $password = $this->hasher->hashPassword($user, 'Passw0rd');
        $user->setPassword($password);

        $manager->persist($user);
        $manager->flush();

        $this->addReference('user1', $user);
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
}
