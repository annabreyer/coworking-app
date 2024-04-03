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

    public function findLastBusinessDay(): ?BusinessDay
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findBusinessDaysAfterDate(\DateTimeInterface $date)
    {
        return $this->createQueryBuilder('b')
            ->where('b.date > :date')
            ->setParameter('date', $date)
            ->orderBy('b.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
