<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projets actifs visibles par l'utilisateur :
     * - TEAM dont il est membre,
     * - PERSONAL dont il est owner.
     *
     * @return Project[]
     */
    public function findVisibleFor(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->andWhere('p.isActive = true')
            ->andWhere(
                '(p.scope = :team AND m = :user) OR (p.scope = :personal AND p.owner = :user)'
            )
            ->setParameter('team', ProjectScope::TEAM)
            ->setParameter('personal', ProjectScope::PERSONAL)
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Projets personnels d'un utilisateur (pour son profil).
     *
     * @return Project[]
     */
    public function findPersonalProjects(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.scope = :personal')
            ->andWhere('p.owner = :user')
            ->setParameter('personal', ProjectScope::PERSONAL)
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Projets d'équipe dont l'utilisateur est membre (pour son profil).
     *
     * @return Project[]
     */
    public function findTeamProjectsForMember(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.members', 'm')
            ->andWhere('p.scope = :team')
            ->andWhere('m = :user')
            ->setParameter('team', ProjectScope::TEAM)
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les projets d'équipe (pour l'admin).
     *
     * @return Project[]
     */
    public function findTeamProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.scope = :team')
            ->setParameter('team', ProjectScope::TEAM)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
