<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\User;

/**
 * Loads a regular user and an admin user for login and user action tests.
 */
class UserAndAdminFixture extends AbstractUserFixture
{
    public const USER_EMAIL = 'user.one@example.org';
    public const ADMIN_EMAIL = 'admin@example.org';
    public const PASSWORD = 'Passw0rd';

    public function load(\Doctrine\Persistence\ObjectManager $manager): void
    {
        // Regular user
        $user = $this->createUser(self::USER_EMAIL, self::PASSWORD);
        $manager->persist($user);
        $this->addReference('user.one', $user);

        // Admin user
        $admin = $this->createUser(self::ADMIN_EMAIL, self::PASSWORD, ['ROLE_SUPER_ADMIN']);
        $manager->persist($admin);
        $this->addReference('admin', $admin);

        $manager->flush();
    }
} 