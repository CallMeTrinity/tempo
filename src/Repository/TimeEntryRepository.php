<?php

namespace App\Repository;

use App\Entity\TimeEntry;
use App\Entity\User;
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
}
