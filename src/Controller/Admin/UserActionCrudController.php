<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\UserAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserAction::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, 'delete')
                     ->remove(Crud::PAGE_DETAIL, 'delete')
                     ->remove(Crud::PAGE_INDEX, 'new')
                     ->remove(Crud::PAGE_INDEX, 'edit')
                     ->remove(Crud::PAGE_DETAIL, 'edit')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('user');
        yield TextField::new('form');
        yield TextField::new('requestUri');
        yield TextField::new('dataString');
    }
}
