<?php

declare(strict_types = 1);

namespace App\Controller\Admin;

use App\EasyAdmin\UserBookingsField;
use App\EasyAdmin\UserInvoicesField;
use App\EasyAdmin\UserVouchersField;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public function __construct()
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
                     ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('fullName');
            yield EmailField::new('email');
            yield BooleanField::new('isActive');
            yield BooleanField::new('isVerified');
            yield DateField::new('createdAt');
            yield AssociationField::new('bookings');
            yield CollectionField::new('unpaidInvoices');
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield FormField::addTab('User Details');

            yield FormField::addColumn(6);
            yield FormField::addFieldset('Personal Information');
            yield TextField::new('fullName');
            yield EmailField::new('email');
            yield TelephoneField::new('mobilePhone');
            yield DateField::new('birthdate');
            yield BooleanField::new('isActive');
            yield BooleanField::new('isVerified');
            yield DateField::new('createdAt');

            yield FormField::addFieldset('Address');
            yield TextField::new('street');
            yield TextField::new('postCode');
            yield TextField::new('city');

            yield FormField::addColumn(6);
            yield FormField::addFieldset('Legal Information');
            yield DateField::new('acceptedCodeOfConduct');
            yield DateField::new('acceptedDataProtection');
            yield CollectionField::new('acceptedTermsOfUse')
            ->setEntryIsComplex()
            ->renderExpanded()
            ->useEntryCrudForm();

            yield FormField::addTab('Bookings');
            yield UserBookingsField::new('bookings', '');

            yield FormField::addTab('Vouchers');
            yield UserVouchersField::new('vouchers', '');

            yield FormField::addTab('Invoices');
            yield UserInvoicesField::new('invoices', '');
        }

        if (Crud::PAGE_EDIT === $pageName) {
            yield TextField::new('firstName');
            yield TextField::new('lastName');
            yield EmailField::new('email');
            yield TextField::new('mobilePhone');
            yield DateField::new('birthdate');
            yield BooleanField::new('isActive');
            yield BooleanField::new('isVerified');
            yield TextField::new('street');
            yield TextField::new('postCode');
            yield TextField::new('city');
            yield DateField::new('createdAt');
            yield DateField::new('acceptedCodeOfConduct');
            yield DateField::new('acceptedDataProtection');
            yield AssociationField::new('acceptedTermsOfUse');
            yield CollectionField::new('bookings')
                                 ->renderExpanded()
                                 ->setEntryIsComplex()
                                 ->useEntryCrudForm()
                                 ->setFormTypeOption('label', false)
                                 ->hideOnIndex()
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
        }
    }
}
