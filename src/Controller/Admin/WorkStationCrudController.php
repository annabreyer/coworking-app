<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\WorkStation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkStationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkStation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name');
        yield AssociationField::new('room');
        yield BooleanField::new('isOpen');
    }
}
