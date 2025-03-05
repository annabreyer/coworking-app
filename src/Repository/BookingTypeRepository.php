<?php

namespace App\Repository;

use App\Entity\BookingType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingType>
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
        return $this->findBy(['isActive' => true]);
    }

}
