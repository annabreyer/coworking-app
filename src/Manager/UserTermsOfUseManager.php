<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\User;
use App\Entity\UserTermsOfUse;
use App\Repository\TermsOfUseRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserTermsOfUseManager
{
    public function __construct(private TermsOfUseRepository $termsOfUseRepository, private EntityManagerInterface $entityManager)
    {
    }

    public function saveAcceptanceTermsOfUse(User $user): void
    {
        $currentTermsOfUse = $this->termsOfUseRepository->findLatest();

        if (null === $currentTermsOfUse) {
            throw new \Exception('No terms of use found');
        }

        $userTermsOfUse = new UserTermsOfUse($user, $currentTermsOfUse);
        $userTermsOfUse->setAcceptedOn(new \DateTime());

        $this->entityManager->persist($userTermsOfUse);
        $this->entityManager->flush();
    }
}
