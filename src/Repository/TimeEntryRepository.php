<?php

namespace App\Repository;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\Status;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntry>
 */
class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    /**
     * @return TimeEntry[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndDate(User $user, \DateTimeInterface $date): ?TimeEntry
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TimeEntry[]
     */
    public function findByUserBetween(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.date BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TimeEntry[]
     * @throws \DateMalformedStringException
     */
    public function findByUserForMonth(User $user, int $year, int $month): array
    {
        $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $to = $from->modify('last day of this month');

        return $this->findByUserBetween($user, $from, $to);
    }

    /**
     * Toutes les entrées en attente d'approbation, hors entrées des admins
     * (un admin ne soumet pas ses propres heures).
     *
     * @return TimeEntry[]
     */
    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :s')
            ->andWhere('u.role != :admin')
            ->setParameter('s', Status::SUBMITTED)
            ->setParameter('admin', \App\Enum\Roles::ADMIN)
            ->leftJoin('t.user', 'u')->addSelect('u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->addOrderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TimeEntry[]
     */
    public function findRecentByUser(User $user, int $limit = 30): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Entrées d'un utilisateur dont le statut figure dans la liste donnée.
     * Utilisé pour requalifier les statuts lors du basculement du mode de suivi.
     *
     * @param Status[] $statuses
     *
     * @return TimeEntry[]
     */
    public function findByUserAndStatuses(User $user, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getResult();
    }

    public function countPendingApproval(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->leftJoin('t.user', 'u')
            ->andWhere('t.status = :s')
            ->andWhere('u.role != :admin')
            ->setParameter('s', Status::SUBMITTED)
            ->setParameter('admin', \App\Enum\Roles::ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
