<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\UserTermsOfUse;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;

class UserTermsOfUseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserTermsOfUse::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
                     ->remove(Crud::PAGE_INDEX, Action::NEW)
                     ->remove(Crud::PAGE_INDEX, Action::EDIT)
                     ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('termsOfUse');
        yield DateField::new('acceptedAt');
    }
}
