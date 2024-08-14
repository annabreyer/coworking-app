<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\UserBookingsField;
use App\EasyAdmin\UserInvoicesField;
use App\EasyAdmin\UserVouchersField;
use App\Entity\User;
use App\Service\RegistrationService;
use Doctrine\ORM\QueryBuilder;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly RegistrationService $registrationService)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
                     ->andWhere('entity.isActive = :isActive')
                     ->setParameter('isActive', true)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['firstName' => 'ASC', 'lastName' => 'ASC'])
                     ->setPaginatorPageSize(50)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
                     ->add('isActive')
                     ->add('isVerified')
                     ->add('createdAt')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendEmailVerificationAction = Action::new('sendEmailVerification', 'Resend Email Verification')
                                             ->linkToCrudAction('sendEmailVerification')
                                             ->setIcon('fa fa-envelope')
                                             ->setHtmlAttributes(['class' => 'btn btn-primary'])
                                             ->displayIf(static function ($entity) {
                                                 return false === $entity->isVerified();
                                             })
        ;

        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
                     ->remove(Crud::PAGE_INDEX, Action::NEW)
                     ->add(Crud::PAGE_DETAIL, $sendEmailVerificationAction)
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
                                 ->useEntryCrudForm()
            ;

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

    public function sendEmailVerification(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $user = $context->getEntity()->getInstance();
        if (!$user instanceof User) {
            throw new \RuntimeException('This entity is not a User.');
        }

        $this->registrationService->sendRegistrationEmail($user);

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl()
        ;

        return $this->redirect($targetUrl);
    }
}
