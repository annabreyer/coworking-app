<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Voucher;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class VoucherCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Voucher::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
        ;
        yield Field::new('value');
        yield Field::new('code');
        yield Field::new('expiryDate');
        yield Field::new('useDate');
        yield AssociationField::new('user');
        yield AssociationField::new('voucherType');
        yield AssociationField::new('invoice');
    }
}
