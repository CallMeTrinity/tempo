<?php

namespace App\Controller;

use App\Entity\BlacklistedEmail;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\ProjectScope;
use App\Enum\Status;
use App\Form\ProjectType;
use App\Project\ProjectColors;
use App\Project\ProjectIcons;
use App\Repository\BlacklistedEmailRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\TimesheetService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(TimeEntryRepository $entries, UserRepository $users): Response
    {
        if ($users->countUnverified() > 0) {
            return $this->redirectToRoute('app_admin_registrations');
        }
        if ($entries->countPendingApproval() > 0) {
            return $this->redirectToRoute('app_admin_entries');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(
        UserRepository $users,
        TimeEntryRepository $entries,
        TimesheetService $timesheet,
    ): Response {
        /** @var User $me */
        $me = $this->getUser();
        $now = new \DateTimeImmutable('today');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $rows = [];
        foreach ($users->findAllExcept($me) as $u) {
            $stats = $timesheet->computeMonthlyStats($u, $year, $month);
            $rows[] = [
                'user' => $u,
                'monthTotal' => $stats['totalHours'],
                'expectedHours' => $stats['expectedHours'],
                'overtime' => $stats['overtimeHours'],
                'deficit' => $stats['deficitHours'],
                'daysFilled' => $stats['daysFilled'],
                'expectedDays' => $stats['expectedWorkingDays'],
            ];
        }

        return $this->render('admin/users.html.twig', [
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'pendingCount' => $entries->countPendingApproval(),
            'unverifiedCount' => $users->countUnverified(),
        ]);
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/users/{id<\d+>}', name: 'user_detail', methods: ['GET'])]
    public function userDetail(
        User $user,
        TimeEntryRepository $entries,
        TimesheetService $timesheet,
        UserRepository $users,
        Request $request,
    ): Response {
        /** @var User $me */
        $me = $this->getUser();
        if ($user->getId() === $me->getId()) {
            return $this->redirectToRoute('app_admin_users');
        }

        $year = (int) ($request->query->get('year') ?? new DateTimeImmutable()->format('Y'));
        $month = (int) ($request->query->get('month') ?? new DateTimeImmutable()->format('n'));
        if ($month < 1 || $month > 12) {
            $month = (int) new DateTimeImmutable()->format('n');
        }

        $stats = $timesheet->computeMonthlyStats($user, $year, $month);
        $recent = $entries->findRecentByUser($user, 60);

        $prev = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month))->modify('-1 month');
        $next = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month))->modify('+1 month');

        return $this->render('admin/user_detail.html.twig', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'stats' => $stats,
            'recentEntries' => $recent,
            'prev' => ['year' => (int) $prev->format('Y'), 'month' => (int) $prev->format('n')],
            'next' => ['year' => (int) $next->format('Y'), 'month' => (int) $next->format('n')],
            'pendingCount' => $entries->countPendingApproval(),
            'unverifiedCount' => $users->countUnverified(),
        ]);
    }

    #[Route('/entries', name: 'entries', methods: ['GET'])]
    public function pendingEntries(TimeEntryRepository $entries, UserRepository $users): Response
    {
        $flat = $entries->findPendingApproval();
        $groups = [];
        foreach ($flat as $entry) {
            $user = $entry->getUser();
            $isoYear = (int) $entry->getDate()->format('o');
            $isoWeek = (int) $entry->getDate()->format('W');
            $key = $user->getId() . '_' . $isoYear . '-' . str_pad((string) $isoWeek, 2, '0', STR_PAD_LEFT);

            if (!isset($groups[$key])) {
                $monday = new DateTimeImmutable()->setISODate($isoYear, $isoWeek, 1)->setTime(0, 0);
                $groups[$key] = [
                    'user' => $user,
                    'year' => $isoYear,
                    'week' => $isoWeek,
                    'weekStart' => $monday,
                    'weekEnd' => $monday->modify('+4 days'),
                    'entries' => [],
                    'totalHours' => 0.0,
                    'daysCount' => 0,
                ];
            }
            $groups[$key]['entries'][] = $entry;
            $groups[$key]['totalHours'] += $entry->getHoursWorked();
            if ($entry->getDayType()->isProductive()) {
                ++$groups[$key]['daysCount'];
            }
        }

        return $this->render('admin/entries.html.twig', [
            'groups' => array_values($groups),
            'pendingCount' => $entries->countPendingApproval(),
            'unverifiedCount' => $users->countUnverified(),
        ]);
    }

    #[Route('/users/{userId<\d+>}/weeks/{year<\d{4}>}/{week<\d{1,2}>}/approve', name: 'week_approve', methods: ['POST'])]
    public function weekApprove(
        int $userId,
        int $year,
        int $week,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        TimeEntryRepository $entries,
    ): Response {
        return $this->bulkUpdateWeek($userId, $year, $week, Status::APPROVED, $request, $em, $users, $entries);
    }

    #[Route('/users/{userId<\d+>}/weeks/{year<\d{4}>}/{week<\d{1,2}>}/review', name: 'week_review', methods: ['POST'])]
    public function weekReview(
        int $userId,
        int $year,
        int $week,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        TimeEntryRepository $entries,
    ): Response {
        return $this->bulkUpdateWeek($userId, $year, $week, Status::TO_BE_REVIEWED, $request, $em, $users, $entries);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function bulkUpdateWeek(
        int $userId,
        int $year,
        int $week,
        Status $newStatus,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        TimeEntryRepository $entries,
    ): Response {
        if ($week < 1 || $week > 53) {
            throw $this->createNotFoundException();
        }
        $user = $users->find($userId);
        if ($user === null) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_week_' . $userId . '_' . $year . '_' . $week, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $monday = new DateTimeImmutable()->setISODate($year, $week, 1)->setTime(0, 0);
        $sunday = $monday->modify('+6 days');

        $touched = 0;
        foreach ($entries->findByUserBetween($user, \DateTime::createFromImmutable($monday), \DateTime::createFromImmutable($sunday)) as $entry) {
            if ($entry->getStatus() === Status::SUBMITTED) {
                $entry->setStatus($newStatus)->setUpdatedAt(new DateTimeImmutable());
                ++$touched;
            }
        }
        $em->flush();

        $action = $newStatus === Status::APPROVED ? 'approuvée' : 'renvoyée pour modification';
        if ($touched === 0) {
            $this->addFlash('info', 'Aucune entrée à mettre à jour pour cette semaine.');
        } else {
            $this->addFlash('success', sprintf(
                'Semaine %d (%s) : %d entrée%s %s%s.',
                $week,
                $user->getFullName() ?? $user->getEmail(),
                $touched,
                $touched > 1 ? 's' : '',
                $action,
                $touched > 1 ? 's' : '',
            ));
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_entries'));
    }

    #[Route('/entries/{id<\d+>}/approve', name: 'entry_approve', methods: ['POST'])]
    public function approve(TimeEntry $entry, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_action_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if ($entry->getStatus() !== Status::SUBMITTED) {
            $this->addFlash('error', 'Seules les entrées soumises peuvent être approuvées.');
        } else {
            $entry->setStatus(Status::APPROVED)->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Entrée du ' . $entry->getDate()->format('d/m/Y') . ' (' . $entry->getUser()?->getFullName() . ') approuvée.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_entries'));
    }

    #[Route('/entries/{id<\d+>}/review', name: 'entry_review', methods: ['POST'])]
    public function sendBackToReview(TimeEntry $entry, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_action_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if ($entry->getStatus() === Status::DRAFT || $entry->getStatus() === Status::TO_BE_REVIEWED) {
            $this->addFlash('error', 'Cette entrée n\'est pas en attente d\'approbation.');
        } else {
            $entry->setStatus(Status::TO_BE_REVIEWED)->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Entrée renvoyée pour modification.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_entries'));
    }

    /* -----------------------------------------------------------------
     * Modération des comptes
     * -----------------------------------------------------------------*/

    #[Route('/registrations', name: 'registrations', methods: ['GET'])]
    public function registrations(
        UserRepository $users,
        TimeEntryRepository $entries,
        BlacklistedEmailRepository $blacklist,
    ): Response {
        return $this->render('admin/registrations.html.twig', [
            'pendingUsers' => $users->findUnverified(),
            'pendingCount' => $entries->countPendingApproval(),
            'unverifiedCount' => $users->countUnverified(),
            'blacklisted' => $blacklist->findAllOrdered(),
        ]);
    }

    #[Route('/users/{id<\d+>}/approve-account', name: 'account_approve', methods: ['POST'])]
    public function approveAccount(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('account_action_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if ($user->isAdmin()) {
            $this->addFlash('error', 'Un compte admin n\'a pas besoin d\'être validé.');
        } elseif ($user->isVerified()) {
            $this->addFlash('info', 'Ce compte est déjà validé.');
        } else {
            $user->setIsVerified(true)->setUpdatedAt(new DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', sprintf('Compte de %s validé.', $user->getFullName() ?? $user->getEmail()));
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_registrations'));
    }

    #[Route('/users/{id<\d+>}/reject-account', name: 'account_reject', methods: ['POST'])]
    public function rejectAccount(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('account_action_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if ($user->isAdmin()) {
            $this->addFlash('error', 'Un compte admin ne peut pas être refusé via ce flux.');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_registrations'));
        }

        /** @var User $me */
        $me = $this->getUser();
        $email = (string) $user->getEmail();
        $label = $user->getFullName() ?? $email;
        $reason = sprintf('Refusé par %s', $me->getFullName() ?? $me->getEmail());

        // On supprime d'abord le user (cascade Doctrine sur ses TimeEntries
        // n'est pas configurée → suppression manuelle des entrées d'abord
        // pour éviter une contrainte FK).
        foreach ($user->getTimeEntries() as $entry) {
            $em->remove($entry);
        }
        $em->remove($user);

        // Ajout à la blacklist (entité indépendante, persiste même après
        // suppression du user via onDelete SET NULL sur blacklisted_by).
        $em->persist(new BlacklistedEmail($email, $me, $reason));

        $em->flush();

        $this->addFlash('success', sprintf('Compte de %s refusé et email %s ajouté à la liste noire.', $label, $email));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_registrations'));
    }

    #[Route('/blacklist/{id<\d+>}/remove', name: 'blacklist_remove', methods: ['POST'])]
    public function removeFromBlacklist(BlacklistedEmail $entry, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('blacklist_remove_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        $email = $entry->getEmail();
        $em->remove($entry);
        $em->flush();
        $this->addFlash('success', sprintf('%s retiré de la liste noire.', $email));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_admin_registrations'));
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(ProjectRepository $projects): Response
    {
        return $this->render('admin/projects.html.twig', [
            'projects' => $projects->findTeamProjects(),
        ]);
    }

    #[Route('/projects/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function newProject(Request $request, EntityManagerInterface $em): Response
    {
        $now = new DateTimeImmutable();
        $project = (new Project())
            ->setScope(ProjectScope::TEAM)
            ->setOwner(null)
            ->setIsActive(true)
            ->setIcon(ProjectIcons::DEFAULT)
            ->setColor(ProjectColors::DEFAULT)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On force le scope au cas où (le champ n'est pas exposé au form).
            $project->setScope(ProjectScope::TEAM)->setOwner(null);
            $em->persist($project);
            $em->flush();
            $this->addFlash('success', sprintf('Projet « %s » créé.', $project->getName()));

            return $this->redirectToRoute('app_admin_projects');
        }

        return $this->render('admin/project_form.html.twig', [
            'form' => $form,
            'project' => $project,
            'mode' => 'new',
        ]);
    }

    #[Route('/projects/{id<\d+>}/edit', name: 'project_edit', methods: ['GET', 'POST'])]
    public function editProject(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setUpdatedAt(new DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', sprintf('Projet « %s » mis à jour.', $project->getName()));

            return $this->redirectToRoute('app_admin_projects');
        }

        return $this->render('admin/project_form.html.twig', [
            'form' => $form,
            'project' => $project,
            'mode' => 'edit',
        ]);
    }

    #[Route('/projects/{id<\d+>}/toggle', name: 'project_toggle', methods: ['POST'])]
    public function toggleProject(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('project_toggle_' . $project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $project->setIsActive(!$project->isActive())->setUpdatedAt(new DateTimeImmutable());
        $em->flush();
        $this->addFlash('success', $project->isActive()
            ? sprintf('Projet « %s » activé.', $project->getName())
            : sprintf('Projet « %s » désactivé.', $project->getName()));

        return $this->redirectToRoute('app_admin_projects');
    }
}
