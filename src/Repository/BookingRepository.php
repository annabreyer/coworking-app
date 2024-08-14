<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockAwareTrait;

/**
 * @extends ServiceEntityRepository<Booking>
 *
 * @method Booking|null find($id, $lockMode = null, $lockVersion = null)
 * @method Booking|null findOneBy(array $criteria, array $orderBy = null)
 * @method Booking[]    findAll()
 * @method Booking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookingRepository extends ServiceEntityRepository
{
    use ClockAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function countBookingsForRoomOnDay(int|Room $roomId, \DateTimeInterface $date): int
    {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.room', 'r')
            ->join('b.businessDay', 'bd')
            ->where('r.id = :roomId')
            ->andWhere('bd.date = :date')
            ->andWhere('b.isCancelled = false')
            ->setParameter('roomId', $roomId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }

    /**
     * @return Booking[]
     */
    public function findBookingsForUserAfterDate(int|User $userId, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->join('b.businessDay', 'bd')
            ->where('u.id = :userId')
            ->andWhere('bd.date > :date')
            ->andWhere('b.isCancelled = false')
            ->setParameter('userId', $userId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Booking[]
     */
    public function findBookingsForUserBeforeDate(int|User $userId, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->join('b.businessDay', 'bd')
            ->where('u.id = :userId')
            ->andWhere('bd.date > :date')
            ->andWhere('b.isCancelled = false')
            ->setParameter('userId', $userId)
            ->setParameter('date', $date)
            ->orderBy('bd.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Booking[]
     */
    public function findBookingsForUserAndYear(int|User $userId, string $year): array
    {
        $start = new \DateTimeImmutable($year . '-01-01');
        $end   = new \DateTimeImmutable($year . '-12-31');

        if ($this->now() < $end) {
            $end = $this->now();
        }

        return $this->findBookingsForUserBetween($userId, $start, $end);
    }

    /**
     * @return Booking[]
     */
    public function findBookingsForUserBetween(int|User $userId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->join('b.businessDay', 'bd')
            ->where('u.id = :userId')
            ->andWhere('bd.date >= :start')
            ->andWhere('bd.date <= :end')
            ->setParameter('userId', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('bd.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Booking[]
     */
    public function findUnfinishedBookingsSince(\DateTimeInterface $dateTime): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.amount is NULL')
            ->andWhere('b.createdAt <= :dateTime')
            ->setParameter('dateTime', $dateTime)
            ->getQuery()
            ->getResult()
        ;
    }
}
