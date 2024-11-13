<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Manager\BookingManager;
use App\Manager\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class BookingCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly InvoiceManager $invoiceManager,
        private readonly BookingManager $bookingManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
                     ->andWhere('entity.isCancelled = :isCancelled')
                     ->setParameter('isCancelled', false)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['businessDay.date' => 'DESC', 'user' => 'ASC', 'createdAt' => 'DESC'])
                     ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
                     ->add('user')
                     ->add('room')
                     ->add('businessDay')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $cancelAction = Action::new('cancelBooking', 'Stornieren')
                              ->linkToCrudAction('cancelBooking')
                              ->displayIf(static function (Booking $booking) {
                                  return false === $booking->isCancelled();
                              })
        ;

        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, 'delete')
                     ->remove(Crud::PAGE_DETAIL, 'delete')
                     ->remove(Crud::PAGE_INDEX, 'edit')
                     ->remove(Crud::PAGE_DETAIL, 'edit')
                     ->add(Crud::PAGE_DETAIL, $cancelAction)
                     ->add(Crud::PAGE_INDEX, $cancelAction)
                     ->add(Crud::PAGE_EDIT, $cancelAction)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Booking Details');
            yield DateField::new('businessDay.date')
                           ->setLabel('Date')
            ;
            yield AssociationField::new('user');
            yield AssociationField::new('room');
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
            ;
            yield DateField::new('createdAt');
            yield BooleanField::new('invoice.isFullyPaid')
                              ->renderAsSwitch(false)
            ;
        }
        if (Crud::PAGE_DETAIL === $pageName) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Booking Details');
            yield DateField::new('businessDay.date')
                           ->setLabel('Date')
            ;
            yield AssociationField::new('user');
            yield AssociationField::new('room');
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
            ;
            yield FormField::addColumn(6);
            yield FormField::addFieldset('PaymentDetails');
            yield AssociationField::new('invoice')
                                  ->setDisabled()
            ;

            if (null !== $this->getContext()->getEntity()->getInstance()->getInvoice()) {
                yield MoneyField::new('invoice.amount')
                                ->setCurrency('EUR')
                                ->setLabel('Invoice Amount')
                ;
                yield DateField::new('invoice.date')
                               ->setLabel('Invoice Date')
                ;
                yield BooleanField::new('invoice.isFullyPaid')
                                  ->renderAsSwitch(false)
                ;
            }
        }

        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Booking Details');
            yield AssociationField::new('businessDay')
                                  ->setQueryBuilder(static function ($queryBuilder) {
                                      return $queryBuilder
                                          ->orderBy('entity.date', 'ASC')
                                      ;
                                  })
            ;
            yield AssociationField::new('user')
                                  ->autocomplete()
            ;
            yield AssociationField::new('room');
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setHelp('Rechnung wird automatisch mit diesem Betrag erstellt.')
            ;
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Booking) {
            return;
        }

        $this->invoiceManager->createInvoiceFromBooking($entityInstance, $entityInstance->getAmount(), true);

        parent::persistEntity($entityManager, $entityInstance);
    }

    #[AdminAction(routePath: '/booking/{entityId}/cancel', routeName: 'cancel', methods: ['POST'])]
    public function cancelBooking(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $targetUrl = $adminUrlGenerator->setRoute('admin_booking_index')->generateUrl();
        $booking   = $context->getEntity()->getInstance();

        if (!$booking instanceof Booking) {
            $this->addFlash('danger', 'Booking not found.');

            return $this->redirect($targetUrl);
        }

        $this->bookingManager->cancelBooking($booking);
        $this->container->get('event_dispatcher')->dispatch(new AfterEntityUpdatedEvent($booking));

        return $this->redirect($targetUrl);
    }
}
