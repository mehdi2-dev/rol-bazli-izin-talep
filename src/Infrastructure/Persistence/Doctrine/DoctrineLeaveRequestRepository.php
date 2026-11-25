<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Exception\NotFound;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\Department;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

/** Adaptör: LeaveRequestRepository portunu Doctrine ORM ile uygular. */
final class DoctrineLeaveRequestRepository implements LeaveRequestRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(int $id): LeaveRequest
    {
        return $this->em->find(LeaveRequest::class, $id)
            ?? throw new NotFound('İzin talebi bulunamadı.');
    }

    public function findByEmployee(User $employee): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(LeaveRequest::class, 'l')
            ->andWhere('l.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('l.period.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDepartment(Department $department, ?LeaveStatus $status = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(LeaveRequest::class, 'l')
            ->join('l.employee', 'u')
            ->andWhere('u.department = :department')
            ->setParameter('department', $department)
            ->orderBy('l.period.startDate', 'ASC');

        if ($status !== null) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status->value);
        }

        return $qb->getQuery()->getResult();
    }

    public function hasOverlapping(User $employee, LeavePeriod $period): bool
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(LeaveRequest::class, 'l')
            ->andWhere('l.employee = :employee')
            ->andWhere('l.status IN (:statuses)')
            ->andWhere('l.period.startDate <= :end')
            ->andWhere('l.period.endDate >= :start')
            ->setParameter('employee', $employee)
            ->setParameter('statuses', array_map(
                fn (LeaveStatus $s) => $s->value,
                array_filter(LeaveStatus::cases(), fn (LeaveStatus $s) => $s->blocksCalendar()),
            ))
            ->setParameter('start', $period->startDate()->format('Y-m-d'))
            ->setParameter('end', $period->endDate()->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function save(LeaveRequest $leaveRequest): void
    {
        $this->em->persist($leaveRequest);
        $this->em->flush();
    }
}
