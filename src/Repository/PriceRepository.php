<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Price;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Price>
 *
 * @method Price|null find($id, $lockMode = null, $lockVersion = null)
 * @method Price|null findOneBy(array $criteria, array $orderBy = null)
 * @method Price[]    findAll()
 * @method Price[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Price::class);
    }

    /**
     * @return Price[]
     */
    public function findActivePrices(): array
    {
        return $this->createQueryBuilder('p')
                    ->andWhere('p.isActive = :isActive')
                    ->setParameter('isActive', true)
                    ->getQuery()
                    ->getResult()
        ;
    }

    /**
     * @return Price[]
     */
    public function findActiveVoucherPrices(): array
    {
        return $this->createQueryBuilder('p')
                    ->andWhere('p.isActive = :isActive')
                    ->andWhere('p.isVoucher = :isVoucher')
                    ->setParameter('isActive', true)
                    ->setParameter('isVoucher', true)
                    ->getQuery()
                    ->getResult()
        ;
    }

    public function findActiveUnitaryPrice(): Price
    {
        return $this->createQueryBuilder('p')
                    ->andWhere('p.isActive = :isActive')
                    ->andWhere('p.isUnitary = :isUnitary')
                    ->setParameter('isActive', true)
                    ->setParameter('isUnitary', true)
                    ->getQuery()
                    ->getSingleResult();
    }
}
