<?php

namespace App\Controller\Admin;

use App\Entity\BusinessDay;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BusinessDayCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BusinessDay::class;
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
        yield IdField::new('id');
        yield DateField::new('date');
        yield BooleanField::new('isOpen');
        yield TextField::new('weekDayShort')
                       ->onlyOnIndex();
        yield AssociationField::new('bookings');
    }
}
