<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Price;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class PriceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Price::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('name');
        yield MoneyField::new('amount')
                        ->setCurrency('EUR')
        ;
        yield Field::new('isActive');
        yield FormField::addFieldset('Type')
                       ->setHelp('Der Preis kann nur eine der 3 Optionen sein, entweder Einzelpreis, Abo oder Mehrfachkarte.')
        ;
        yield Field::new('isUnitary');
        yield Field::new('isSubscription');
        yield Field::new('isVoucher');

        yield AssociationField::new('voucherType')
                              ->setFormTypeOption('query_builder', static function (EntityRepository $entityRepository) {
                                  return $entityRepository->createQueryBuilder('vt')
                                                          ->andWhere('vt.isActive = true')
                                                          ->orderBy('vt.name', 'ASC')
                                  ;
                              })
                              ->hideOnIndex()
                              ->setHelp('Nur aktive GutscheinTypen werden angezeigt.')
        ;
    }
}
