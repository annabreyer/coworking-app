<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Withdrawal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Withdrawal>
 *
 * @method Withdrawal|null find($id, $lockMode = null, $lockVersion = null)
 * @method Withdrawal|null findOneBy(array $criteria, array $orderBy = null)
 * @method Withdrawal[]    findAll()
 * @method Withdrawal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WithdrawalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Withdrawal::class);
    }
}
