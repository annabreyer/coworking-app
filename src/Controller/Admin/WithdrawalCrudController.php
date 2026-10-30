<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Entity\Withdrawal;
use App\Manager\WithdrawalManager;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class WithdrawalCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly WithdrawalManager $withdrawalManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Withdrawal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setEntityLabelInSingular('Widerruf')
                     ->setEntityLabelInPlural('Widerrufe')
                     ->setDefaultSort(['receivedAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $processAction = Action::new('processWithdrawal', 'Widerruf bearbeiten')
                               ->linkToCrudAction('processWithdrawal')
                               ->displayIf(static function (Withdrawal $withdrawal) {
                                   return WithdrawalManager::canBeProcessed($withdrawal);
                               })
        ;

        return parent::configureActions($actions)
                     ->add(Crud::PAGE_INDEX, $processAction)
                     ->add(Crud::PAGE_DETAIL, $processAction)
                     ->remove(Crud::PAGE_INDEX, Action::NEW)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('receivedAt')
                           ->setFormTypeOption('disabled', true)
                           ->setTimezone('Europe/Berlin');
        yield TextField::new('name')
                       ->setFormTypeOption('disabled', true);
        yield EmailField::new('email')
                        ->setFormTypeOption('disabled', true);
        yield TextField::new('contractReference')
                       ->setFormTypeOption('disabled', true);
        yield AssociationField::new('invoice');
        yield AssociationField::new('user');
        yield ChoiceField::new('status')
                         ->setChoices([
                             'Eingegangen' => Withdrawal::STATUS_RECEIVED,
                             'Bearbeitet'  => Withdrawal::STATUS_PROCESSED,
                             'Abgelehnt'   => Withdrawal::STATUS_REJECTED,
                         ]);
        yield TextareaField::new('adminComment')
                           ->hideOnIndex();
    }

    #[AdminRoute('/{entityId}/process', name: 'process', options: ['methods' => ['GET', 'POST']])]
    public function processWithdrawal(AdminContext $context, AdminUrlGenerator $adminUrlGenerator, Request $request): Response
    {
        $withdrawal = $context->getEntity()->getInstance();
        if (!$withdrawal instanceof Withdrawal) {
            throw new \LogicException('Withdrawal instance is missing.');
        }

        $detailUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($withdrawal->getId())
            ->generateUrl()
        ;

        $invoice = $withdrawal->getInvoice();
        if (null === $invoice || false === WithdrawalManager::canBeProcessed($withdrawal)) {
            $this->addFlash('danger', $this->translator->trans('admin.withdrawal.not_processable', [], 'flash'));

            return $this->redirect($detailUrl);
        }

        if ($request->isMethod('POST')) {
            if (false === $this->isCsrfTokenValid('process_withdrawal', $request->getPayload()->getString('token'))) {
                $this->addFlash('danger', $this->translator->trans('form.general.csrf_token_invalid', [], 'flash'));

                return $this->redirect($detailUrl);
            }

            try {
                $result = $this->withdrawalManager->processWithdrawal(
                    $withdrawal,
                    $request->getPayload()->getString('adminComment'),
                    $request->getPayload()->getBoolean('todaysBookingUnused')
                );
            } catch (\LogicException|\InvalidArgumentException $exception) {
                $this->addFlash('danger', $this->translator->trans('admin.withdrawal.error', ['%message%' => $exception->getMessage()], 'flash'));

                return $this->redirect($detailUrl);
            }

            if (Withdrawal::STATUS_REJECTED === $result->status) {
                $this->addFlash('warning', $this->translator->trans('admin.withdrawal.rejected', [], 'flash'));
            } else {
                $this->addFlash('success', $this->translator->trans('admin.withdrawal.success', [], 'flash'));
                $this->addFlash($result->needsManualRefund() ? 'warning' : 'info', $this->withdrawalManager->getDefaultAdminComment($result));
            }

            return $this->redirect($detailUrl);
        }

        $today            = $this->now()->format('Y-m-d');
        $hasTodaysBooking = $invoice->getBookings()->exists(static function (int $key, Booking $booking) use ($today) {
            return false === $booking->isCancelled() && $booking->getBusinessDay()?->getDate()->format('Y-m-d') === $today;
        });

        return $this->render('admin/withdrawal/process.html.twig', [
            'withdrawal'       => $withdrawal,
            'invoice'          => $invoice,
            'hasTodaysBooking' => $hasTodaysBooking,
            'detailUrl'        => $detailUrl,
        ]);
    }
}
