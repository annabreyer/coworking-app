<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Withdrawal;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WithdrawalCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Withdrawal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setEntityLabelInSingular('Widerruf')
                     ->setEntityLabelInPlural('Widerrufe')
                     ->setDefaultSort(['receivedAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->add(Crud::PAGE_INDEX, Action::DETAIL)
                     ->remove(Crud::PAGE_INDEX, Action::NEW)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('receivedAt')
                           ->setFormTypeOption('disabled', true)
                           ->setTimezone('Europe/Berlin');
        yield TextField::new('name')
                       ->setFormTypeOption('disabled', true);
        yield EmailField::new('email')
                        ->setFormTypeOption('disabled', true);
        yield TextField::new('contractReference')
                       ->setFormTypeOption('disabled', true);
        yield AssociationField::new('invoice');
        yield AssociationField::new('user');
        yield ChoiceField::new('status')
                         ->setChoices([
                             'Eingegangen' => Withdrawal::STATUS_RECEIVED,
                             'Bearbeitet'  => Withdrawal::STATUS_PROCESSED,
                             'Abgelehnt'   => Withdrawal::STATUS_REJECTED,
                         ]);
        yield TextareaField::new('adminComment')
                           ->hideOnIndex();
    }
}
