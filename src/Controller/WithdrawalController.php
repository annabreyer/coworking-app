<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Manager\WithdrawalManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WithdrawalController extends AbstractController
{
    public const STEP_REVIEW  = 'review';
    public const STEP_CONFIRM = 'confirm';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WithdrawalManager $withdrawalManager,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $withdrawalLimiter,
    ) {
    }

    #[Route('/widerruf', name: 'withdrawal_policy', methods: ['GET'])]
    public function withdrawalPolicy(): Response
    {
        return $this->render('withdrawal/policy.html.twig');
    }

    #[Route('/vertrag-widerrufen', name: 'withdrawal_form', methods: ['GET', 'POST'])]
    public function withdraw(Request $request): Response
    {
        $user     = $this->getUser();
        $user     = $user instanceof User ? $user : null;
        $response = new Response();

        if (false === $request->isMethod('POST')) {
            return $this->renderWithdrawalForm($response, $user, [
                'name'              => $user?->getFullName() ?? '',
                'email'             => $user?->getEmail()    ?? '',
                'contractReference' => $request->query->getString('contract'),
            ]);
        }

        $data = [
            'name'              => trim($request->request->getString('name')),
            'email'             => trim($request->request->getString('email')),
            'contractReference' => trim($request->request->getString('contractReference')),
        ];

        if (false === $this->isCsrfTokenValid('withdrawal', $request->getPayload()->getString('token'))) {
            $this->addFlash('error', $this->translator->trans('form.general.csrf_token_invalid', [], 'flash'));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderWithdrawalForm($response, $user, $data);
        }

        $errors = $this->validateWithdrawalData($data);
        if (false === empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);

            return $this->renderWithdrawalForm($response, $user, $data);
        }

        if (self::STEP_CONFIRM !== $request->request->getString('step')) {
            return $this->render('withdrawal/confirm.html.twig', ['data' => $data]);
        }

        $limiter = $this->withdrawalLimiter->create($request->getClientIp());
        if (false === $limiter->consume()->isAccepted()) {
            $this->addFlash('error', $this->translator->trans('form.withdrawal.too_many_requests', [], 'flash'));
            $response->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS);

            return $this->renderWithdrawalForm($response, $user, $data);
        }

        $withdrawal = $this->withdrawalManager->saveWithdrawal(
            $data['name'],
            $data['email'],
            $data['contractReference'],
            $user
        );

        return $this->render('withdrawal/received.html.twig', ['withdrawal' => $withdrawal]);
    }

    /**
     * @param array<string, string> $data
     *
     * @return string[]
     */
    private function validateWithdrawalData(array $data): array
    {
        $constraints = [
            'name'              => [new NotBlank(), new Length(max: 255)],
            'email'             => [new NotBlank(), new Email(), new Length(max: 180)],
            'contractReference' => [new NotBlank(), new Length(max: 255)],
        ];

        $errors = [];
        foreach ($constraints as $field => $fieldConstraints) {
            if (0 < $this->validator->validate($data[$field], $fieldConstraints)->count()) {
                $errors[] = $this->translator->trans('form.withdrawal.invalid_' . $field, [], 'flash');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data
     */
    private function renderWithdrawalForm(Response $response, ?User $user, array $data): Response
    {
        return $this->render('withdrawal/form.html.twig', [
            'data'     => $data,
            'invoices' => null === $user ? [] : $this->withdrawalManager->getInvoicesWithinWithdrawalPeriod($user),
        ], $response);
    }
}
