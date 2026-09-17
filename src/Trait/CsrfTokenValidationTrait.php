<?php

declare(strict_types = 1);

namespace App\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for validating CSRF tokens in controllers
 */
trait CsrfTokenValidationTrait
{
    private function validateCsrfToken(Request $request, string $tokenId, string $tokenField, Response $response): bool
    {
        $submittedToken = $request->getPayload()->getString($tokenField);

        if (false === $this->isCsrfTokenValid($tokenId, $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('form.general.csrf_token_invalid', [], 'flash'));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            return false;
        }

        return true;
    }
}