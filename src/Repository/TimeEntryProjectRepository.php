<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\TimeEntryProject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntryProject>
 */
class TimeEntryProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntryProject::class);
    }

    /**
     * Total d'heures affectées, agrégé par projet, pour une liste de projets.
     * Les projets sans affectation sont absents de la map (repli à 0 côté appelant).
     *
     * @param Project[] $projects
     *
     * @return array<int, float> projectId => total d'heures
     */
    public function sumHoursByProject(array $projects): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (Project $project): ?int => $project->getId(),
            $projects,
        )));

        if ($ids === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('tep')
            ->select('IDENTITY(tep.project) AS projectId', 'SUM(tep.hours) AS total')
            ->andWhere('tep.project IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('tep.project')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['projectId']] = (float) $row['total'];
        }

        return $map;
    }

    /**
     * Total d'heures affectées par un utilisateur donné, agrégé par projet.
     * Contrairement à sumHoursByProject(), filtre sur l'auteur de l'entrée :
     * pertinent pour les projets d'équipe, où chaque membre veut voir ses
     * propres heures plutôt que le cumul de l'équipe.
     *
     * @param Project[] $projects
     *
     * @return array<int, float> projectId => total d'heures de l'utilisateur
     */
    public function sumHoursByProjectForUser(array $projects, User $user): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (Project $project): ?int => $project->getId(),
            $projects,
        )));

        if ($ids === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('tep')
            ->select('IDENTITY(tep.project) AS projectId', 'SUM(tep.hours) AS total')
            ->innerJoin('tep.timeEntry', 'te')
            ->andWhere('tep.project IN (:ids)')
            ->andWhere('te.user = :user')
            ->setParameter('ids', $ids)
            ->setParameter('user', $user)
            ->groupBy('tep.project')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['projectId']] = (float) $row['total'];
        }

        return $map;
    }
}
