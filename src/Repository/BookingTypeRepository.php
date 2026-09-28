<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingType>
 *
 * @method BookingType|null find($id, $lockMode = null, $lockVersion = null)
 * @method BookingType|null findOneBy(array $criteria, array $orderBy = null)
 * @method BookingType[]    findAll()
 * @method BookingType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookingTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingType::class);
    }

    /**
     * @return BookingType[]
     */
    public function findActiveTypes(): array
    {
        return $this->createQueryBuilder('t')
                    ->andWhere('t.isActive = :isActive')
                    ->setParameter('isActive', true)
                    ->orderBy('t.name', 'ASC')
                    ->getQuery()
                    ->getResult()
        ;
    }
}
