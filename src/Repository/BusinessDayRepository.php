<?php

namespace App\Repository;

use App\Entity\BusinessDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessDay>
 *
 * @method BusinessDay|null find($id, $lockMode = null, $lockVersion = null)
 * @method BusinessDay|null findOneBy(array $criteria, array $orderBy = null)
 * @method BusinessDay[]    findAll()
 * @method BusinessDay[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BusinessDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessDay::class);
    }

//    /**
//     * @return BusinessDay[] Returns an array of BusinessDay objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BusinessDay
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
