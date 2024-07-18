<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TermsOfUse;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class TermsOfUseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TermsOfUse::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('version');
        yield Field::new('date');
        yield Field::new('path');
    }
}
