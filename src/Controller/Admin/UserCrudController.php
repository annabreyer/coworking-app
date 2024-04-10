<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
                     ->hideOnDetail()
        ;
        yield TextField::new('fullName')
                       ->hideOnForm()
                       ->hideOnDetail()
        ;
        yield ChoiceField::new('roles')
                         ->allowMultipleChoices()
                         ->setChoices([
                             'ROLE_USER'        => 'ROLE_USER',
                             'ROLE_ADMIN'       => 'ROLE_ADMIN',
                             'ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
                         ])
                         ->hideOnIndex()
        ;
        yield TextField::new('firstName')
                       ->hideOnIndex()
        ;
        yield TextField::new('lastName')
                       ->hideOnIndex()
        ;
        yield EmailField::new('email');
        yield TextField::new('mobilePhone');
        yield DateField::new('birthdate');
        yield BooleanField::new('isActive');
        yield BooleanField::new('isVerified');
        yield DateField::new('createdAt');
        yield DateField::new('acceptedCodeOfConduct')
                       ->hideOnIndex()
        ;
        yield DateField::new('acceptedDataProtection')
                       ->hideOnIndex()
        ;
        yield AssociationField::new('acceptedTermsOfUse')
                              ->hideOnForm()
                              ->hideOnIndex()
        ;
        yield CollectionField::new('bookings')
                             ->renderExpanded()
                             ->setEntryIsComplex()
                             ->useEntryCrudForm()
                             ->setFormTypeOption('label', false)
        ;
    }
}
