<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PasswordForgottenService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class PasswordForgottenServiceTest extends KernelTestCase
{
    public function testPasswordForgottenEmailThrowsExceptionIfUserHasNoEmail(): void
    {
        $mailer                   = $this->createMock(MailerInterface::class);
        $translator               = $this->createMock(TranslatorInterface::class);
        $passwordForgottenService = new PasswordForgottenService($mailer, $translator);

        $user  = new User();
        $token = new ResetPasswordToken('token', new \DateTime('+1 day'), time() - 3600);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User email is required to send password forgotten email');
        $passwordForgottenService->sendPasswordForgottenEmail($user, $token);
    }

    public function testSendPasswordForgottenEmailSendsEmail(): void
    {
        $mailer                   = static::getContainer()->get(MailerInterface::class);
        $translator               = $this->createMock(TranslatorInterface::class);
        $passwordForgottenService = new PasswordForgottenService($mailer, $translator);

        $user = new User();
        $user->setEmail('test@test.de');
        $token = new ResetPasswordToken('token', new \DateTime('+1 day'), time() - 3600);
        $passwordForgottenService->sendPasswordForgottenEmail($user, $token);

        static::assertEmailCount(1);
    }
}
