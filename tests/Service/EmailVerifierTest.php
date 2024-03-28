<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifierTest extends KernelTestCase
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

    public function testGetEmailConfirmationContextChecksUser(): void
    {
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $emailVerifier     = new EmailVerifier($verifyEmailHelper, $this->entityManager);

        $user = new User();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This user does not have a valid Id or email');

        $emailVerifier->getEmailConfirmationContext('verify_email', $user);
    }

    public function testGetEmailConfirmationContextReturnsContext(): void
    {
        $verifyEmailHelper = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $emailVerifier     = new EmailVerifier($verifyEmailHelper, $this->entityManager);

        $user    = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $context = $emailVerifier->getEmailConfirmationContext('app_verify_email', $user);

        self::assertIsArray($context);
        self::assertArrayHasKey('signedUrl', $context);
        self::assertArrayHasKey('expiresAtMessageKey', $context);
        self::assertArrayHasKey('expiresAtMessageData', $context);
        self::assertIsArray($context['expiresAtMessageData']);
    }

    public function testHandleEmailConfirmationChecksUser(): void
    {
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $emailVerifier     = new EmailVerifier($verifyEmailHelper, $this->entityManager);

        $user = new User();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This user does not have a valid Id or email');

        $emailVerifier->handleEmailConfirmation(new Request(), $user);
    }

    public function testHandleEmailConformationSetsIsVerified(): void
    {
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $emailVerifier     = new EmailVerifier($verifyEmailHelper, $this->entityManager);

        $user    = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $request = new Request();

        $emailVerifier->handleEmailConfirmation($request, $user);

        self::assertTrue($user->IsVerified());
    }
}
