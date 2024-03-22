<?php

namespace App\Repository;

use App\Entity\TermsOfUse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TermsOfUse>
 *
 * @method TermsOfUse|null find($id, $lockMode = null, $lockVersion = null)
 * @method TermsOfUse|null findOneBy(array $criteria, array $orderBy = null)
 * @method TermsOfUse[]    findAll()
 * @method TermsOfUse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TermsOfUseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TermsOfUse::class);
    }

    //    /**
    //     * @return TermsOfUse[] Returns an array of TermsOfUse objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TermsOfUse
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
