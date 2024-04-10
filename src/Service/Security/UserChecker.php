<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPostAuth(UserInterface $user): void
    {
        if (false === $user instanceof User) {
            return;
        }

        if (false === $user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Your account is not active. Please contact us.');
        }
    }

    public function checkPreAuth(UserInterface $user): void
    {
        // nothing to do here
    }
}
