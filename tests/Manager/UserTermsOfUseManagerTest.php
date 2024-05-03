<?php

declare(strict_types=1);

namespace App\Tests\Manager;

use App\Entity\TermsOfUse;
use App\Entity\User;
use App\Manager\UserTermsOfUseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserTermsOfUseManagerTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel              = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
                                      ->get('doctrine')
                                      ->getManager()
        ;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testSaveAcceptanceTermsOfUseCreatesUserTermsOfUse(): void
    {
        $termsOfUseRepository = $this->entityManager->getRepository(TermsOfUse::class);

        $user = new User();
        $user->setEmail('test2@test.de')
             ->setMobilePhone('1234567890')
             ->setFirstName('Test')
             ->setLastName('Test')
        ;

        $userTermsOfUseManager = new UserTermsOfUseManager($termsOfUseRepository, $this->entityManager);
        $userTermsOfUseManager->saveAcceptanceTermsOfUse($user);

        static::assertNotNull($user->getAcceptedTermsOfUse());
    }
}
