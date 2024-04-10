<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
                     ->hideOnForm()
        ;
        yield AssociationField::new('businessDay');
        yield AssociationField::new('user');
        yield AssociationField::new('room');
    }
}
