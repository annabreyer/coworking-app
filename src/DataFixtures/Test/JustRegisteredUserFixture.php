<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\User;

/**
 * Loads a single user who has just registered (no accepted terms).
 * For use in registration-related tests.
 */
class JustRegisteredUserFixture extends AbstractUserFixture
{
    public function load(\Doctrine\Persistence\ObjectManager $manager): void
    {
        $user = $this->createUser(self::JUST_REGISTERED_EMAIL, self::PASSWORD);
        // No accepted data protection or code of conduct
        $manager->persist($user);
        $this->addReference('justRegisteredUser', $user);
        $manager->flush();
    }
} 