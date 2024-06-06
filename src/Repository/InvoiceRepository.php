<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Manager\PaymentManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findLatestInvoiceForYear(string $year): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('YEAR(i.date) = :year')
            ->setParameter('year', $year)
            ->orderBy('i.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findInvoiceForBookingAndUserAndPaymentType(int $bookingId, int $userId, string $paymentType): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->join('i.bookings', 'b')
            ->join('i.user', 'u')
            ->join('i.payments', 'p')
            ->andWhere('b.id = :bookingId')
            ->andWhere('u.id = :userId')
            ->andWhere('p.type = :paymentType')
            ->setParameter('bookingId', $bookingId)
            ->setParameter('userId', $userId)
            ->setParameter('paymentType', $paymentType)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    
    public function getUnpaidVoucherInvoicesForUser(int $userId): Collection
    {
        return $this->createQueryBuilder('i')
            ->join('i.user', 'u')
            ->join('i.payments', 'p')
            ->join('i.vouchers', 'v')
            ->andWhere('u.id = :userId')
            ->andWhere('SUM(p.amount) < SUM(i.amount)')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
        ;
    }
}
