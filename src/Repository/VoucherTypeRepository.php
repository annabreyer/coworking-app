<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VoucherType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoucherType>
 *
 * @method VoucherType|null find($id, $lockMode = null, $lockVersion = null)
 * @method VoucherType|null findOneBy(array $criteria, array $orderBy = null)
 * @method VoucherType[]    findAll()
 * @method VoucherType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VoucherTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoucherType::class);
    }

    //    /**
    //     * @return VoucherTypes[] Returns an array of VoucherTypes objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?VoucherTypes
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
