<?php

namespace App\Repository;

use App\Entity\WorkStation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkStation>
 *
 * @method WorkStation|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkStation|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkStation[]    findAll()
 * @method WorkStation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkStationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkStation::class);
    }

//    /**
//     * @return WorkStation[] Returns an array of WorkStation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?WorkStation
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
