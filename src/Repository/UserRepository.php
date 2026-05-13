<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findAllExcept(User $user): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id != :id')
            ->setParameter('id', $user->getId())
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes utilisateurs (non-admins) en attente de validation par un admin.
     *
     * @return User[]
     */
    public function findUnverified(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isVerified = false')
            ->andWhere('u.role != :admin')
            ->setParameter('admin', \App\Enum\Roles::ADMIN)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnverified(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = false')
            ->andWhere('u.role != :admin')
            ->setParameter('admin', \App\Enum\Roles::ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
