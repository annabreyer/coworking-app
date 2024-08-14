<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\UserAction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class InactiveUserCrudController extends UserCrudController
{
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
                     ->andWhere('entity.isActive = :isActive')
                     ->setParameter('isActive', false)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setDefaultSort(['firstName' => 'ASC', 'lastName' => 'ASC'])
                     ->setPaginatorPageSize(50)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
                     ->add(Crud::PAGE_INDEX, Action::DELETE)
                     ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action) {
                         $action->displayIf(static function ($entity) {
                             return $entity->getBookings()->isEmpty() && $entity->getInvoices()->isEmpty() && $entity->getVouchers()->isEmpty();
                         });

                         return $action;
                     })
        ;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance->getBookings()->isEmpty() || !$entityInstance->getInvoices()->isEmpty() || !$entityInstance->getVouchers()->isEmpty()) {
            throw new \RuntimeException('This user can not be deleted because he has bookings, invoices or vouchers.');
        }

        $adminActions = $entityManager->getRepository(AdminAction::class)->findBy(['user' => $entityInstance]);
        foreach ($adminActions as $adminAction) {
            $entityManager->remove($adminAction);
        }

        $userActions = $entityManager->getRepository(UserAction::class)->findBy(['user' => $entityInstance]);
        foreach ($userActions as $userAction) {
            $entityManager->remove($userAction);
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
