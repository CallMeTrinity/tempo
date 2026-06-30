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

    /**
     * Tous les projets (personnels comme d'équipe) sur lesquels l'utilisateur
     * a affecté des heures, avec son cumul par projet. Trié par heures décroissantes.
     *
     * @return list<array{project: Project, hours: float}>
     */
    public function findProjectHoursForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('tep')
            ->select('IDENTITY(tep.project) AS projectId', 'SUM(tep.hours) AS hours')
            ->innerJoin('tep.timeEntry', 'te')
            ->andWhere('te.user = :user')
            ->setParameter('user', $user)
            ->groupBy('tep.project')
            ->orderBy('hours', 'DESC')
            ->getQuery()
            ->getResult();

        if ($rows === []) {
            return [];
        }

        $projectsById = [];
        foreach ($this->getEntityManager()->getRepository(Project::class)
            ->findBy(['id' => array_map(static fn (array $row): int => (int) $row['projectId'], $rows)]) as $project) {
            $projectsById[$project->getId()] = $project;
        }

        $result = [];
        foreach ($rows as $row) {
            $project = $projectsById[(int) $row['projectId']] ?? null;
            if ($project !== null) {
                $result[] = ['project' => $project, 'hours' => (float) $row['hours']];
            }
        }

        return $result;
    }

    /**
     * Tous les utilisateurs ayant affecté des heures sur un projet donné,
     * avec leur cumul. Trié par heures décroissantes.
     *
     * @return list<array{user: User, hours: float}>
     */
    public function findMemberHoursForProject(Project $project): array
    {
        $rows = $this->createQueryBuilder('tep')
            ->select('IDENTITY(te.user) AS userId', 'SUM(tep.hours) AS hours')
            ->innerJoin('tep.timeEntry', 'te')
            ->andWhere('tep.project = :project')
            ->setParameter('project', $project)
            ->groupBy('te.user')
            ->orderBy('hours', 'DESC')
            ->getQuery()
            ->getResult();

        if ($rows === []) {
            return [];
        }

        $usersById = [];
        foreach ($this->getEntityManager()->getRepository(User::class)
            ->findBy(['id' => array_map(static fn (array $row): int => (int) $row['userId'], $rows)]) as $user) {
            $usersById[$user->getId()] = $user;
        }

        $result = [];
        foreach ($rows as $row) {
            $user = $usersById[(int) $row['userId']] ?? null;
            if ($user !== null) {
                $result[] = ['user' => $user, 'hours' => (float) $row['hours']];
            }
        }

        return $result;
    }
}
