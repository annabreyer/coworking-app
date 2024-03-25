<?php

declare(strict_types=1);

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

    public function findLatest(): ?TermsOfUse
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult()
        ;
    }
}
