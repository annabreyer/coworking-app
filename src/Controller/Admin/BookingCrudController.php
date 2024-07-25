<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Manager\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Symfony\Component\Clock\ClockAwareTrait;

class BookingCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly InvoiceManager $invoiceManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
                     ->add('user')
                     ->add('room')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, 'delete')
                     ->remove(Crud::PAGE_INDEX, 'edit')
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
                                  ->setDisabled();

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
                                  ->setQueryBuilder(function ($queryBuilder) {
                                      return $queryBuilder
                                          ->andWhere('entity.date >= :today')
                                          ->setParameter('today', $this->now())
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
    }
}
