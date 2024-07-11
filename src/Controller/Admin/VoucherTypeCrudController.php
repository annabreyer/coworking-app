<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VoucherType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class VoucherTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VoucherType::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
        ;
        yield Field::new('units');
        yield Field::new('validityMonths');
        yield Field::new('unitaryValue');
    }
}
