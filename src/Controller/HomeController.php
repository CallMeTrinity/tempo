<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\DayType;
use App\Enum\Status;
use App\Form\DayPlanningType;
use App\Form\TimeEntryType;
use App\Repository\TimeEntryRepository;
use App\Service\TimesheetService;
use DateMalformedStringException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HomeController extends AbstractController
{
    /**
     * @throws DateMalformedStringException
     */
    #[Route('/home', name: 'app_home', methods: ['GET', 'POST'])]
    public function home(
        Request $request,
        EntityManagerInterface $em,
        TimeEntryRepository $repo,
        TimesheetService $timesheet,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $weekParam = $request->query->get('week');
        $dateParam = $request->query->get('date');

        if ($weekParam !== null && $weekParam !== '') {
            $weekStart = $this->resolveWeekStart($weekParam);
            $selectedDate = $weekStart !== null
                ? DateTime::createFromImmutable($weekStart)
                : new DateTime('today');
        } else {
            $selectedDate = $this->resolveSelectedDate($dateParam);
            $weekStart = $timesheet->normalizeMonday($selectedDate);
        }

        $contractStart = $user->getContractStartDate();
        if ($contractStart !== null && $selectedDate < $contractStart) {
            $this->addFlash('error', sprintf(
                'Le %s est antérieur à votre date de début de contrat (%s).',
                $selectedDate->format('d/m/Y'),
                $contractStart->format('d/m/Y'),
            ));

            return $this->redirectToRoute('app_home', [
                'date' => $contractStart->format('Y-m-d'),
            ]);
        }

        $existing = $repo->findOneByUserAndDate($user, $selectedDate);
        $isEdit = $existing !== null;
        $timeEntry = $isEdit ? $existing : $this->buildPrefilledEntry($user, $selectedDate);

        // Lock de workflow : SUBMITTED ou APPROVED n'est plus éditable par l'user.
        $isReadOnly = $isEdit && !$timeEntry->getStatus()->isEditableByUser();
        // Jour planifié non-travaillé (PTO/UTO/OFF) : pas de form de saisie.
        $isPlannedNonWork = $isEdit && in_array(
            $timeEntry->getDayType(),
            [DayType::PTO, DayType::UTO, DayType::OFF],
            true,
        );
        $showForm = !$isReadOnly && !$isPlannedNonWork;

        $form = $this->createForm(TimeEntryType::class, $timeEntry, ['user' => $user]);
        $form->get('isRemote')->setData($timeEntry->getDayType() === DayType::REMOTE);
        $form->handleRequest($request);

        if ($showForm && $form->isSubmitted() && $form->isValid()) {
            $timeEntry->setUpdatedAt(new \DateTimeImmutable());
            if (!$isEdit) {
                $em->persist($timeEntry);
            }
            $em->flush();

            $this->addFlash('success', $isEdit
                ? 'Entrée mise à jour pour le ' . $timeEntry->getDate()->format('d/m/Y') . '.'
                : 'Entrée enregistrée pour le ' . $timeEntry->getDate()->format('d/m/Y') . '.'
            );

            return $this->redirectToRoute('app_home', ['date' => $timeEntry->getDate()->format('Y-m-d')]);
        }

        // Week-ends toujours chômés : on s'arrête au vendredi pour l'affichage
        // et on filtre les entrées potentielles de week-end pour les stats.
        $weekEnd = $weekStart->modify('+4 days');
        $weekCells = array_values(array_filter(
            $timesheet->buildWeekView($user, $weekStart),
            static fn (array $c) => $c['isoWeekday'] <= 5,
        ));

        $weekEntries = [];
        $weekSubmittableCount = 0;
        foreach ($weekCells as $cell) {
            if ($cell['entry'] !== null) {
                $weekEntries[] = $cell['entry'];
                if ($cell['entry']->getStatus()->canBeSubmittedByUser()) {
                    ++$weekSubmittableCount;
                }
            }
        }
        $weekStats = $timesheet->computeWeeklyStats($user, $weekEntries);

        $allEntries = $repo->findByUser($user->getId());
        $grandTotal = array_sum(array_map(static fn (TimeEntry $e) => $e->getHoursWorked(), $allEntries));

        // Formulaire de planification (jours non-travaillés ou TT à l'avance)
        $planningForm = $this->createForm(DayPlanningType::class, [
            'startDate' => $selectedDate,
            'endDate' => $selectedDate,
            'dayType' => DayType::PTO,
        ], [
            'action' => $this->generateUrl('app_planning_create'),
        ]);

        $response = $this->render('home/home.html.twig', [
            'timeEntryForm' => $form,
            'planningForm' => $planningForm,
            'isEdit' => $isEdit,
            'isPlannedNonWork' => $isPlannedNonWork,
            'isReadOnly' => $isReadOnly,
            'showForm' => $showForm,
            'currentEntry' => $isEdit ? $timeEntry : null,
            'selectedDate' => $selectedDate,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekCells' => $weekCells,
            'weekTotal' => $weekStats['workedHours'],
            'weeklyTarget' => $weekStats['weeklyTarget'],
            'weeklyProgress' => $weekStats['progress'],
            'overtimeHours' => $weekStats['overtimeHours'],
            'deficitHours' => $weekStats['deficitHours'],
            'daysWorked' => $weekStats['daysWorked'],
            'recentEntries' => array_slice($allEntries, 0, 10),
            'grandTotal' => $grandTotal,
            'weekIso' => $weekStart->format('o-\WW'),
            'weekSubmittableCount' => $weekSubmittableCount,
        ]);

        // Saisie soumise mais invalide : statut 422 pour que Turbo réaffiche le
        // frame avec les erreurs (un 200 serait interprété comme un succès).
        if ($showForm && $form->isSubmitted()) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    #[Route('/time-entry/{id}/delete', name: 'app_time_entry_delete', methods: ['POST'])]
    public function delete(TimeEntry $entry, Request $request, EntityManagerInterface $em): Response
    {
        if ($entry->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete_entry_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if (!$entry->getStatus()->isEditableByUser()) {
            $this->addFlash('error', 'Cette entrée ne peut plus être supprimée (statut « ' . $entry->getStatus()->getLabel() . ' »).');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
        }

        $em->remove($entry);
        $em->flush();

        $this->addFlash('success', 'Entrée supprimée.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/time-entry/{id}/unsubmit', name: 'app_time_entry_unsubmit', methods: ['POST'])]
    public function unsubmit(TimeEntry $entry, Request $request, EntityManagerInterface $em): Response
    {
        if ($entry->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('unsubmit_entry_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if (!$entry->getStatus()->canBeUnsubmittedByUser()) {
            $this->addFlash('error', 'Cette entrée ne peut plus être retirée (statut « ' . $entry->getStatus()->getLabel() . ' »).');
        } else {
            $entry->setStatus(Status::DRAFT)->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Entrée du ' . $entry->getDate()->format('d/m/Y') . ' remise en brouillon.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
    }

    /**
     * Soumet en bloc toutes les entrées éditables (DRAFT/TO_BE_REVIEWED) de la
     * semaine indiquée. Le paramètre `week=YYYY-Wnn` cible la semaine ISO.
     */
    #[Route('/week/submit', name: 'app_week_submit', methods: ['POST'])]
    public function submitWeek(
        Request $request,
        EntityManagerInterface $em,
        TimeEntryRepository $repo,
        TimesheetService $timesheet,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isAdmin()) {
            $this->addFlash('error', 'Un admin ne soumet pas ses heures.');
            return $this->redirectToRoute('app_home');
        }

        if ($user->isIndependent()) {
            $this->addFlash('error', 'En suivi personnel, vos heures n\'ont pas besoin d\'être soumises.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isCsrfTokenValid('submit_week_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $weekParam = (string) $request->request->get('week', '');
        $weekStart = $this->resolveWeekStart($weekParam);
        if ($weekStart === null) {
            $weekStart = $timesheet->normalizeMonday(new DateTime('today'));
        }
        $weekEnd = $weekStart->modify('+6 days');

        $entries = $repo->findByUserBetween(
            $user,
            DateTime::createFromImmutable($weekStart),
            DateTime::createFromImmutable($weekEnd),
        );

        $submitted = 0;
        $skipped = 0;
        foreach ($entries as $entry) {
            if ($entry->getStatus()->canBeSubmittedByUser()) {
                $entry->setStatus(Status::SUBMITTED)->setUpdatedAt(new \DateTimeImmutable());
                ++$submitted;
            } else {
                ++$skipped;
            }
        }
        $em->flush();

        if ($submitted === 0) {
            $this->addFlash('info', 'Aucune entrée à soumettre dans cette semaine.');
        } else {
            $msg = $submitted . ' entrée' . ($submitted > 1 ? 's' : '') . ' soumise' . ($submitted > 1 ? 's' : '') . ' pour validation.';
            if ($skipped > 0) {
                $msg .= ' (' . $skipped . ' déjà ' . ($skipped > 1 ? 'soumises ou approuvées' : 'soumise ou approuvée') . ')';
            }
            $this->addFlash('success', $msg);
        }

        return $this->redirectToRoute('app_home', ['week' => $weekStart->format('o-\WW')]);
    }

    private function resolveSelectedDate(?string $raw): DateTime
    {
        if ($raw !== null && $raw !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $raw);
            if ($d instanceof DateTime) {
                $d->setTime(0, 0);
                return $d;
            }
        }

        return new DateTime('today');
    }

    private function resolveWeekStart(string $raw): ?\DateTimeImmutable
    {
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $raw, $m) !== 1) {
            return null;
        }
        $year = (int) $m[1];
        $week = (int) $m[2];
        if ($week < 1 || $week > 53) {
            return null;
        }

        return (new \DateTimeImmutable())->setISODate($year, $week, 1)->setTime(0, 0);
    }

    /**
     * Pré-remplit une entrée WORKED ou REMOTE (selon defaultRemoteDays).
     * Horaires basés sur user.expectedDailyHours + user.defaultBreakMinutes.
     */
    private function buildPrefilledEntry(User $user, DateTime $date): TimeEntry
    {
        $entry = (new TimeEntry())
            ->setUser($user)
            ->setDate($date)
            ->setStatus($user->isIndependent() ? Status::SELF_TRACKED : Status::DRAFT)
            ->setCreatedAt(new \DateTimeImmutable());

        $isoWd = (int) $date->format('N');
        $isPredefinedRemote = in_array($isoWd, $user->getDefaultRemoteDays(), true);

        if ($isPredefinedRemote) {
            return $entry->setDayType(DayType::REMOTE);
        }

        $entry->setDayType(DayType::WORKED);

        $expectedDaily = $user->getExpectedDailyHours();
        $breakMin = $user->getDefaultBreakMinutes();
        $start = new DateTime('09:00');

        if ($expectedDaily !== null) {
            $minutes = (int) round($expectedDaily * 60) + $breakMin;
            $end = (clone $start)->modify('+' . $minutes . ' minutes');
        } else {
            $end = new DateTime('18:00');
        }

        return $entry
            ->setStartTime($start)
            ->setEndTime($end)
            ->setBreakDuration($breakMin);
    }
}
