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
    #[Route('/', name: 'app_home', methods: ['GET', 'POST'])]
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

        // Si l'entrée existante est non-travaillée (PTO/UTO/OFF), on n'affiche
        // pas le formulaire de saisie : c'est un jour planifié, à supprimer
        // d'abord pour le rouvrir à la saisie.
        $isPlannedNonWork = $isEdit && in_array(
            $timeEntry->getDayType(),
            [DayType::PTO, DayType::UTO, DayType::OFF],
            true,
        );

        $form = $this->createForm(TimeEntryType::class, $timeEntry);
        $form->get('isRemote')->setData($timeEntry->getDayType() === DayType::REMOTE);
        $form->handleRequest($request);

        if (!$isPlannedNonWork && $form->isSubmitted() && $form->isValid()) {
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

        $weekEnd = $weekStart->modify('+6 days');
        $weekCells = $timesheet->buildWeekView($user, $weekStart);

        $weekEntries = [];
        foreach ($weekCells as $cell) {
            if ($cell['entry'] !== null) {
                $weekEntries[] = $cell['entry'];
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

        return $this->render('home/home.html.twig', [
            'timeEntryForm' => $form,
            'planningForm' => $planningForm,
            'isEdit' => $isEdit,
            'isPlannedNonWork' => $isPlannedNonWork,
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
        ]);
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

        $em->remove($entry);
        $em->flush();

        $this->addFlash('success', 'Entrée supprimée.');

        return $this->redirectToRoute('app_home');
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
            ->setStatus(Status::DRAFT)
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
