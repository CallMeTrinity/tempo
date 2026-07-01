<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\DayType;
use App\Form\DayPlanningType;
use App\Repository\TimeEntryRepository;
use App\Service\TimesheetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class MonthController extends AbstractController
{
    #[Route('/month', name: 'app_month_current', methods: ['GET'])]
    public function current(): Response
    {
        $now = new \DateTimeImmutable('today');

        return $this->redirectToRoute('app_month', [
            'year' => (int) $now->format('Y'),
            'month' => (int) $now->format('n'),
        ]);
    }

    #[Route('/month/{year<\d{4}>}/{month<\d{1,2}>}', name: 'app_month', methods: ['GET'])]
    public function month(
        int $year,
        int $month,
        TimeEntryRepository $repo,
        TimesheetService $timesheet,
    ): Response {
        if ($month < 1 || $month > 12) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();

        $today = new \DateTimeImmutable('today');
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        $monthLabel = $first->format('Y-m');

        // Garde « avant contrat » au niveau mois.
        $contractStart = $user->getContractStartDate();
        if ($contractStart !== null) {
            $contractStartImm = \DateTimeImmutable::createFromMutable($contractStart)->setTime(0, 0);
            if ($last < $contractStartImm) {
                $this->addFlash('error', sprintf(
                    'Le mois %s est antérieur à votre date de début de contrat (%s).',
                    $monthLabel,
                    $contractStart->format('d/m/Y'),
                ));

                return $this->redirectToRoute('app_month', [
                    'year' => (int) $contractStartImm->format('Y'),
                    'month' => (int) $contractStartImm->format('n'),
                ]);
            }
        }

        $stats = $timesheet->computeMonthlyStats($user, $year, $month);

        // Grille 7x6 : du lundi qui précède (ou est) le 1er, jusqu'à 41 jours plus tard.
        $gridStart = $timesheet->normalizeMonday($first);
        $entriesByKey = [];
        foreach ($repo->findByUserBetween(
            $user,
            \DateTime::createFromImmutable($gridStart),
            \DateTime::createFromImmutable($gridStart->modify('+41 days')),
        ) as $entry) {
            $entriesByKey[$entry->getDate()->format('Y-m-d')] = $entry;
        }

        $contractStartImm = $contractStart !== null
            ? \DateTimeImmutable::createFromMutable($contractStart)->setTime(0, 0)
            : null;

        $cells = [];
        for ($i = 0; $i < 42; ++$i) {
            $d = $gridStart->modify("+$i days");
            $key = $d->format('Y-m-d');
            $cells[] = [
                'date' => $d,
                'entry' => $entriesByKey[$key] ?? null,
                'isCurrentMonth' => (int) $d->format('n') === $month,
                'isToday' => $d == $today,
                'isFuture' => $d > $today,
                'isBeforeContract' => $contractStartImm !== null && $d < $contractStartImm,
                'isoWeekday' => (int) $d->format('N'),
            ];
        }

        // Navigation mois précédent / suivant.
        // Navigation libre vers le futur pour consulter les jours planifiés.
        $prev = $first->modify('-1 month');
        $next = $first->modify('+1 month');
        $canGoPrev = $contractStartImm === null || $prev->modify('last day of this month') >= $contractStartImm;
        $canGoNext = true;

        $planningForm = $this->createForm(DayPlanningType::class, [
            'startDate' => \DateTime::createFromImmutable($first),
            'endDate' => \DateTime::createFromImmutable($last),
            'dayType' => DayType::PTO,
        ], [
            'action' => $this->generateUrl('app_planning_create'),
        ]);

        return $this->render('month/month.html.twig', [
            'year' => $year,
            'month' => $month,
            'firstOfMonth' => $first,
            'cells' => $cells,
            'stats' => $stats,
            'prev' => ['year' => (int) $prev->format('Y'), 'month' => (int) $prev->format('n')],
            'next' => ['year' => (int) $next->format('Y'), 'month' => (int) $next->format('n')],
            'canGoPrev' => $canGoPrev,
            'canGoNext' => $canGoNext,
            'planningForm' => $planningForm,
        ]);
    }
}
