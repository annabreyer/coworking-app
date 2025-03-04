<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TermsOfUse;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class TermsOfUseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TermsOfUse::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['createdAt' => 'DESC'])
                     ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('version');
        yield Field::new('date');
        yield Field::new('path');
    }
}
