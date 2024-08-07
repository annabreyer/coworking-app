<?php declare(strict_types = 1);

namespace App\Controller;


use App\Repository\TermsOfUseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{

    public function __construct(
        private readonly FileSystem $filesystem,
        private readonly string $legalDirectory
    ) {
    }
    #[Route('/terms-of-use', name: 'app_terms_of_use')]
    public function downloadTermsOfUse(TermsOfUseRepository $termsOfUseRepository): BinaryFileResponse
    {
        $currentTermsOfUse = $termsOfUseRepository->findLatest();

        if (null === $currentTermsOfUse) {
            throw $this->createNotFoundException('No terms of use found');
        }

        $termsOfUsePath = $this->legalDirectory . $currentTermsOfUse->getPath();

        if (false === $this->filesystem->exists($termsOfUsePath)) {
            throw $this->createNotFoundException('No terms of use found');
        }

        return new BinaryFileResponse($termsOfUsePath);
    }

    #[Route('/code-of-conduct', name: 'app_code_of_conduct')]
    public function downloadCodeOfConduct(): BinaryFileResponse
    {
        $codeOfConduct = $this->legalDirectory . 'CodeOfConduct.pdf';

        if (false === $this->filesystem->exists($codeOfConduct)) {
            throw $this->createNotFoundException('No code of conduct found');
        }

        return new BinaryFileResponse($codeOfConduct);
    }

    #[Route('/data-protection', name: 'app_data-protection')]
    public function downloadPrivacyPolicy(): BinaryFileResponse
    {
        $dataProtectionPath = $this->legalDirectory . 'Datenschutzerklärung202408.pdf';

        if (false === $this->filesystem->exists($dataProtectionPath)) {
            throw $this->createNotFoundException('No data protection found');
        }

        return new BinaryFileResponse($dataProtectionPath);
    }
}