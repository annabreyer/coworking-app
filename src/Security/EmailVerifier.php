<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param string $verifyEmailRouteName
     * @param User   $user
     *
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getEmailConfirmationContext(string $verifyEmailRouteName, User $user): array
    {
        if (null === $user->getId() || null === $user->getEmail()) {
            throw new \Exception('This user does not have a valid ID or email');
        }

        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            $user->getEmail()
        );

        $context['signedUrl']            = $signatureComponents->getSignedUrl();
        $context['expiresAtMessageKey']  = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        return $context;
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        if (null === $user->getId() || null === $user->getEmail()) {
            throw new \Exception('This user does not have a valid ID or email');
        }

        $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), (string) $user->getId(), $user->getEmail());

        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
