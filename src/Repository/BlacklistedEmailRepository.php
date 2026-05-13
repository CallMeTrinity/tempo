<?php

namespace App\Repository;

use App\Entity\BlacklistedEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlacklistedEmail>
 */
class BlacklistedEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlacklistedEmail::class);
    }

    public function existsByEmail(string $email): bool
    {
        $email = mb_strtolower(trim($email));

        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.email = :e')
            ->setParameter('e', $email)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return BlacklistedEmail[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.blacklistedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
