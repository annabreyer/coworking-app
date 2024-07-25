<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Payment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_NEW === $pageName) {
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setRequired(true)
            ;
            yield Field::new('date')
                       ->setRequired(true)
            ;
            yield ChoiceField::new('type')
                             ->setChoices(Payment::getPaymentTypes())
                             ->setRequired(true)
            ;
            yield AssociationField::new('voucher');
            yield TextField::new('comment');
        }

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setDisabled()
            ;
            yield Field::new('date')->setDisabled();
            yield Field::new('type')->setDisabled();
            yield TextField::new('comment')->setDisabled();
        }
    }
}
