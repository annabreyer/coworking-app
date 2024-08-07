<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TermsOfUseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TermsController extends AbstractController
{
    public function __construct(
        private readonly FileSystem $filesystem,
        private readonly string $legalDirectory
    ) {
    }
    #[Route('/terms-of-use', name: 'app_terms_of_use')]
    public function termsOfUse(TermsOfUseRepository $termsOfUseRepository): Response
    {
        $currentTermsOfUse = $termsOfUseRepository->findLatest();

        if (null === $currentTermsOfUse) {
            throw $this->createNotFoundException('No terms of use found');
        }

        $termsOfUsePath = $this->legalDirectory . '/' . $currentTermsOfUse->getPath();

        if (false === $this->filesystem->exists($termsOfUsePath)) {
            throw $this->createNotFoundException('No terms of use found');
        }

        return new BinaryFileResponse($termsOfUsePath);
    }

    #[Route('/data-protection', name: 'app_data_protection')]
    public function dataProtection(): Response
    {
        $dataProtectionPath = $this->legalDirectory . '/Datenschutzerklärung202408.pdf';

        if (false === $this->filesystem->exists($dataProtectionPath)) {
            throw $this->createNotFoundException('No data protection found');
        }

        return new BinaryFileResponse($dataProtectionPath);
    }

    #[Route('/code-of-conduct', name: 'app_code_of_conduct')]
    public function codeOfConduct(): Response
    {
        $codeOfConduct = $this->legalDirectory . '/CodeOfConduct.pdf';

        if (false === $this->filesystem->exists($codeOfConduct)) {
            throw $this->createNotFoundException('No code of conduct found');
        }

        return new BinaryFileResponse($codeOfConduct);
    }
}
