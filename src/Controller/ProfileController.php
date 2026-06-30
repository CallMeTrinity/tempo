<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectScope;
use App\Form\ProjectType;
use App\Form\UserProfileType;
use App\Project\ProjectColors;
use App\Project\ProjectIcons;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryProjectRepository;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/profile', name: 'app_profile_')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        TimeEntryRepository $repo,
        ProjectRepository $projects,
        TimeEntryProjectRepository $allocations,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile_index');
        }

        $personalProjects = $projects->findPersonalProjects($user);

        $response = $this->render('profile/profile.html.twig', [
            'user' => $user,
            'userProfileForm' => $form,
            'stats' => $this->computeStats($user, $repo),
            'personalProjects' => $personalProjects,
            'hoursByProject' => $allocations->sumHoursByProject($personalProjects),
        ]);

        // Form soumis mais invalide : statut 422 pour que Turbo réaffiche le
        // frame avec les erreurs (un 200 serait interprété comme un succès).
        if ($form->isSubmitted()) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /**
     * @return array{
     *   totalHours: float,
     *   totalEntries: int,
     *   avgHoursPerEntry: float,
     *   distinctWeeks: int,
     *   avgHoursPerWeek: float,
     *   weeklyTarget: ?float,
     *   targetDelta: ?float,
     *   firstEntryDate: ?\DateTimeInterface,
     *   lastEntryDate: ?\DateTimeInterface,
     * }
     */
    private function computeStats(User $user, TimeEntryRepository $repo): array
    {
        $entries = $repo->findByUser($user->getId());

        $totalHours = 0.0;
        $weeks = [];
        $firstDate = null;
        $lastDate = null;

        foreach ($entries as $entry) {
            $totalHours += $entry->getHoursWorked();
            $weeks[$entry->getDate()->format('o-W')] = true;
            $date = $entry->getDate();
            if ($firstDate === null || $date < $firstDate) {
                $firstDate = $date;
            }
            if ($lastDate === null || $date > $lastDate) {
                $lastDate = $date;
            }
        }

        $count = count($entries);
        $distinctWeeks = count($weeks);
        $avgPerEntry = $count > 0 ? $totalHours / $count : 0.0;
        $avgPerWeek = $distinctWeeks > 0 ? $totalHours / $distinctWeeks : 0.0;
        $target = $user->getWeeklyHours();

        return [
            'totalHours' => round($totalHours, 2),
            'totalEntries' => $count,
            'avgHoursPerEntry' => round($avgPerEntry, 2),
            'distinctWeeks' => $distinctWeeks,
            'avgHoursPerWeek' => round($avgPerWeek, 2),
            'weeklyTarget' => $target,
            'targetDelta' => $target !== null ? round($avgPerWeek - $target, 2) : null,
            'firstEntryDate' => $firstDate,
            'lastEntryDate' => $lastDate,
        ];
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(ProjectRepository $projects, TimeEntryProjectRepository $allocations): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $personalProjects = $projects->findPersonalProjects($user);

        return $this->render('profile/projects.html.twig', [
            'projects' => $personalProjects,
            'hoursByProject' => $allocations->sumHoursByProject($personalProjects),
        ]);
    }

    #[Route('/projects/new', name: 'projects_new', methods: ['GET', 'POST'])]
    public function createProject(EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $now = new DateTimeImmutable();

        $project = (new Project())
            ->setIcon(ProjectIcons::DEFAULT)
            ->setColor(ProjectColors::DEFAULT)
            ->setIsActive(true)
            ->setScope(ProjectScope::PERSONAL)
            ->setOwner($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $form = $this->createForm(ProjectType::class, $project, ['personal' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Scope/owner forcés côté serveur : le formulaire ne les expose pas.
            $project->setScope(ProjectScope::PERSONAL)->setOwner($user);
            $em->persist($project);
            $em->flush();

            $this->addFlash('success', sprintf('Projet « %s » créé.', $project->getName()));

            return $this->redirectToRoute('app_profile_projects');
        }

        return $this->render('profile/project_form.html.twig', [
            'form' => $form,
            'project' => $project,
            'mode' => 'new',
        ]);
    }

    #[Route('/projects/{id<\d+>}/edit', name: 'projects_edit', methods: ['GET', 'POST'])]
    public function editProject(Request $request, EntityManagerInterface $em, Project $project): Response
    {
        $this->assertOwnedPersonalProject($project);

        $form = $this->createForm(ProjectType::class, $project, ['personal' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setUpdatedAt(new DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', sprintf('Projet « %s » mis à jour.', $project->getName()));

            return $this->redirectToRoute('app_profile_projects');
        }

        return $this->render('profile/project_form.html.twig', [
            'form' => $form,
            'project' => $project,
            'mode' => 'edit',
        ]);
    }

    #[Route('/projects/{id<\d+>}/toggle', name: 'projects_toggle', methods: ['POST'])]
    public function toggleProject(
        Project $project,
        Request $request,
        EntityManagerInterface $em,
        TimeEntryProjectRepository $allocations,
    ): Response {
        $this->assertOwnedPersonalProject($project);

        if (!$this->isCsrfTokenValid('project_toggle_' . $project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $project->setIsActive(!$project->isActive())->setUpdatedAt(new DateTimeImmutable());
        $em->flush();

        // Requête Turbo Frame : on ne renvoie que la ligne mise à jour, Turbo
        // remplace le frame en place sans recharger la liste ni la page.
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('profile/_project_row.html.twig', [
                'project' => $project,
                'hours' => $allocations->sumHoursByProject([$project])[$project->getId()] ?? 0.0,
            ]);
        }

        // Repli sans Turbo (JS désactivé) : navigation classique.
        $this->addFlash('success', $project->isActive()
            ? sprintf('Projet « %s » activé.', $project->getName())
            : sprintf('Projet « %s » désactivé.', $project->getName()));

        return $this->redirectToRoute('app_profile_projects');
    }

    /**
     * Garantit que le projet est un projet personnel appartenant à
     * l'utilisateur courant. Bloque l'accès aux projets d'équipe et aux
     * projets personnels d'autrui (403).
     */
    private function assertOwnedPersonalProject(Project $project): void
    {
        if ($project->getScope() !== ProjectScope::PERSONAL || $project->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer ce projet.');
        }
    }
}
