<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['date' => 'DESC', 'user' => 'ASC', 'createdAt' => 'DESC'])
                     ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->onlyOnIndex();

        yield FormField::addColumn(6);
        yield FormField::addFieldset('Booking Details');
        yield AssociationField::new('businessDay');
        yield AssociationField::new('user');
        yield AssociationField::new('room');
        yield MoneyField::new('amount')->setCurrency('EUR');
        yield DateField::new('createdAt');

        yield FormField::addColumn(6);
        yield FormField::addFieldset('PaymentDetails');
        yield AssociationField::new('invoice');
        yield MoneyField::new('invoice.amount')->setCurrency('EUR');
        yield DateField::new('invoice.date');
        yield CollectionField::new('invoice.payments');
    }
}
