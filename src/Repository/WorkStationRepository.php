<?php

declare(strict_types=1);

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

    public function findAllOpen()
    {
        return $this->createQueryBuilder('w')
            ->where('w.isOpen = true')
            ->getQuery()
            ->getResult()
        ;
    }
}
