<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\User;
use App\Manager\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagerTest extends KernelTestCase
{
    public function testSaveUserPasswordSavesPassword(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $user = $container->get('doctrine')
                          ->getManager()
                          ->getRepository(User::class)
                          ->findAll()[0];

        $plainPassword      = 'password';
        $userPasswordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager      = $container->get(EntityManagerInterface::class);

        $userManager = new UserManager($userPasswordHasher, $entityManager);
        $userManager->saveUserPassword($user, $plainPassword);

        self::assertNotSame($plainPassword, $user->getPassword());
    }

    public function testSaveUserPasswordPersistUserIfNotAlreadyPersisted(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $user = new User();
        $user->setEmail('test@test.de')
        ->setMobilePhone('1234567890')
        ->setFirstName('Test')
        ->setLastName('Test');

        $plainPassword      = 'password';
        $userPasswordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager      = $container->get(EntityManagerInterface::class);

        $userManager = new UserManager($userPasswordHasher, $entityManager);
        $userManager->saveUserPassword($user, $plainPassword);

        self::assertNotNull($user->getCreatedAt());
    }
}
