<?php

namespace App\Repository;

use App\Entity\UserTermsOfUse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserTermsOfUse>
 *
 * @method UserTermsOfUse|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserTermsOfUse|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserTermsOfUse[]    findAll()
 * @method UserTermsOfUse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserTermsOfUseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTermsOfUse::class);
    }

    //    /**
    //     * @return UserTermsOfUse[] Returns an array of UserTermsOfUse objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserTermsOfUse
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
