<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VoucherType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class VoucherTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VoucherType::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('name');
        yield Field::new('units');
        yield Field::new('validityMonths');
        yield MoneyField::new('unitaryValue')
                        ->setCurrency('EUR')
        ;
        yield BooleanField::new('isActive');
        yield AssociationField::new('vouchers')
                              ->hideOnForm()
        ;
        yield AssociationField::new('prices')
                              ->hideOnForm()
        ;
    }
}
