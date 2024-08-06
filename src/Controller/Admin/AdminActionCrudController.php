<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class AdminActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdminAction::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['createdAt' => 'DESC', 'user' => 'ASC'])
                     ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('adminUser')
            ->add('user')
            ->add('createdAt')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, 'delete')
            ->remove(Crud::PAGE_INDEX, 'edit')
            ->remove(Crud::PAGE_DETAIL, 'edit')
            ->remove(Crud::PAGE_DETAIL, 'delete')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('adminUser'),
            AssociationField::new('user'),
            TextareaField::new('dataString'),
            DateField::new('createdAt'),
        ];
    }
}
