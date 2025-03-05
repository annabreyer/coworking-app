<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BookingType;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class BookingTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BookingType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['name' => 'ASC'])
                     ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('name');
        yield Field::new('isActive');


        yield AssociationField::new('price')
                              ->setFormTypeOption('query_builder', static function (EntityRepository $entityRepository) {
                                  return $entityRepository->createQueryBuilder('p')
                                                          ->andWhere('p.isActive = true')
                                                          ->orderBy('p.name', 'ASC')
                                  ;
                              })
                              ->hideOnIndex()
                              ->setHelp('Nur aktive Preise werden angezeigt.')
        ;
    }
}
