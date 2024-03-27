<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Manager\UserManager;
use App\Manager\UserTermsOfUseManager;
use App\Security\EmailVerifier;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    protected AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        $kernel             = self::bootKernel();
        $this->databaseTool = $kernel->getContainer()
                                     ->get(DatabaseToolCollection::class)
                                     ->get()
        ;

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

    public function testRegisterUserSavesAcceptedDataProtectionAndCodeOfConduct(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $registrationService = $this->getRegistrationServiceWithEntityManager();
        $user                = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'just.registered@annabreyer.dev']);
        $plainPassword       = 'Passw0rd';

        $registrationService->registerUser($user, $plainPassword);

        $this->assertNotNull($user->getAcceptedDataProtection());
        $this->assertNotNull($user->getAcceptedCodeOfConduct());
    }

    public function testRegisterUserChecksIfDataProtectionIsAlreadyAccepted(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $registrationService = $this->getRegistrationServiceWithEntityManager();
        $user                = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'just.registered@annabreyer.dev']);
        $plainPassword       = 'Passw0rd';
        $user->setAcceptedDataProtection(new \DateTime());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User Registration is finished. User already accepted the data protection policy');
        $registrationService->registerUser($user, $plainPassword);
    }

    public function testRegisterUserChecksIfCodeOfConductIsAlreadyAccepted(): void
    {
        $this->databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $registrationService = $this->getRegistrationServiceWithEntityManager();
        $user                = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'just.registered@annabreyer.dev']);
        $plainPassword       = 'Passw0rd';
        $user->setAcceptedCodeOfConduct(new \DateTime());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User Registration is finished. User already accepted the code of conduct');
        $registrationService->registerUser($user, $plainPassword);
    }

    public function testSendRegistrationEmailThrowsExceptionIfUserHasNoEmail(): void
    {
        $registrationService = $this->getRegistrationServiceWithEntityManager();
        $user                = new User();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User email is required to send registration email');
        $registrationService->sendRegistrationEmail($user);
    }

    public function testSendRegistrationEmailSendsEmail(): void
    {
        $mailer            = static::getContainer()->get(MailerInterface::class);
        $emailVerifier     = $this->createConfiguredMock(EmailVerifier::class,
            ['getEmailConfirmationContext' => [
                'signedUrl'            => 'signedUrl',
                'expiresAtMessageKey'  => 'ExpirationMessageKey',
                'expiresAtMessageData' => ['ExpirationMessageData'],
            ]])
        ;
        $translator        = $this->createMock(TranslatorInterface::class);
        $userManager       = $this->createMock(UserManager::class);
        $termsOfUseManager = $this->createMock(UserTermsOfUseManager::class);

        $registrationService = new RegistrationService($userManager, $emailVerifier, $this->entityManager, $translator,
            $mailer, $termsOfUseManager);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $registrationService->sendRegistrationEmail($user);

        $this->assertEmailCount(1);
    }

    private function getRegistrationServiceWithEntityManager(): RegistrationService
    {
        $mailer            = $this->createMock(MailerInterface::class);
        $emailVerifier     = $this->createMock(EmailVerifier::class);
        $translator        = $this->createMock(TranslatorInterface::class);
        $userManager       = $this->createMock(UserManager::class);
        $termsOfUseManager = $this->createMock(UserTermsOfUseManager::class);

        return new RegistrationService($userManager, $emailVerifier, $this->entityManager, $translator,
            $mailer, $termsOfUseManager);
    }
}
