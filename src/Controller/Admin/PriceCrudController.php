<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Price;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class PriceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Price::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
        ;
        yield Field::new('isActive');
        yield Field::new('amount');
        yield Field::new('isUnitary');
        yield Field::new('isVoucher');
        yield Field::new('isSubscription');
        yield AssociationField::new('voucherType');
    }
}
