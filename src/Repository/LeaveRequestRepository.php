<?php

namespace App\Repository;

use App\Entity\Department;
use App\Entity\LeaveRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 */
class LeaveRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    /**
     * Kullanıcının verilen tarih aralığıyla çakışan, bekleyen veya onaylanmış talepleri.
     *
     * @return LeaveRequest[]
     */
    public function findOverlapping(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.status IN (:statuses)')
            ->andWhere('l.startDate <= :end')
            ->andWhere('l.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('statuses', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Bir departmandaki (ekipteki) çalışanların talepleri. $status verilirse ona göre filtrelenir.
     *
     * @return LeaveRequest[]
     */
    public function findByDepartment(Department $department, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->andWhere('u.department = :department')
            ->setParameter('department', $department)
            ->orderBy('l.startDate', 'ASC');

        if ($status !== null) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
