<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TermsController
{
    #[Route('/terms-of-use', name: 'app_terms_of_use')]
    public function termsOfUse(): Response
    {
        return new Response('Terms of use');
    }

    #[Route('/data-protection', name: 'app_data_protection')]
    public function dataProtection(): Response
    {
        return new Response('Data protection');
    }

    #[Route('/code-of-conduct', name: 'app_code_of_conduct')]
    public function codeOfConduct(): Response
    {
        return new Response('Code of conduct');
    }
}
