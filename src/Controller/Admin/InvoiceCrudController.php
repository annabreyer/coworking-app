<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Invoice;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InvoiceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
        ;
        yield Field::new('amount');
        yield Field::new('number');
        yield Field::new('date');
        yield AssociationField::new('user');
        yield AssociationField::new('bookings');
        yield AssociationField::new('payments');
        yield AssociationField::new('vouchers');
        yield TextField::new('uuid');
        yield TextField::new('payPalOrderId');
    }
}
