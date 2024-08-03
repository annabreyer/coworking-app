<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BusinessDay;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Clock\ClockAwareTrait;

class BusinessDayCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public static function getEntityFqcn(): string
    {
        return BusinessDay::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPaginatorPageSize(31);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('date');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, 'delete')
            ->remove(Crud::PAGE_INDEX, 'edit')
            ->remove(Crud::PAGE_DETAIL, 'edit')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('date');
        yield TextField::new('weekDayLong')
                       ->onlyOnIndex();
        yield BooleanField::new('isOpen');
        yield AssociationField::new('bookings');
    }
}
