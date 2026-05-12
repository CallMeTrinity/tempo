<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\Status;
use App\Form\TimeEntryType;
use App\Repository\TimeEntryRepository;
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
    public function home(Request $request, EntityManagerInterface $em, TimeEntryRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Date sélectionnée via ?date=… (sinon aujourd'hui)
        $selectedDate = $this->resolveSelectedDate($request->query->get('date'));

        // Si une entrée existe déjà pour ce jour → on l'édite, sinon on en crée une
        $existing = $repo->findOneByUserAndDate($user, $selectedDate);
        $isEdit = $existing !== null;

        if ($isEdit) {
            $timeEntry = $existing;
        } else {
            $timeEntry = new TimeEntry()
                ->setDate($selectedDate)
                ->setUser($user)
                ->setStatus(Status::DRAFT)
                ->setStartTime(new DateTime('09:00'))
                ->setEndTime(new DateTime('18:00'))
                ->setBreakDuration(120)
                ->setCreatedAt(new \DateTimeImmutable());
        }

        $form = $this->createForm(TimeEntryType::class, $timeEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeEntry->setUpdatedAt(new \DateTimeImmutable());
            if (!$isEdit) {
                $em->persist($timeEntry);
            }
            $em->flush();

            $this->addFlash('success', $isEdit
                ? 'Entrée mise à jour pour le ' . $timeEntry->getDate()->format('d/m/Y') . '.'
                : 'Entrée enregistrée pour le ' . $timeEntry->getDate()->format('d/m/Y') . '.'
            );

            return $this->redirectToRoute('app_home');
        }

        // Stats — semaine en cours
        [$weekStart, $weekEnd] = $this->currentWeekRange();
        $weekEntries = $repo->findByUserBetween($user, $weekStart, $weekEnd);
        $weekByDate = [];
        $weekTotal = 0.0;
        foreach ($weekEntries as $entry) {
            $weekByDate[$entry->getDate()->format('Y-m-d')] = $entry;
            $weekTotal += $entry->getHoursWorked();
        }

        // Historique global
        $allEntries = $repo->findByUser($user->getId());
        $grandTotal = array_sum(array_map(static fn (TimeEntry $e) => $e->getHoursWorked(), $allEntries));

        $weeklyTarget = $user->getWeeklyHours();
        $weeklyProgress = $weeklyTarget !== null && $weeklyTarget > 0
            ? min(100, round(($weekTotal / $weeklyTarget) * 100))
            : null;

        return $this->render('home/home.html.twig', [
            'timeEntryForm' => $form,
            'isEdit' => $isEdit,
            'selectedDate' => $selectedDate,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekByDate' => $weekByDate,
            'weekTotal' => $weekTotal,
            'weeklyTarget' => $weeklyTarget,
            'weeklyProgress' => $weeklyProgress,
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

    /**
     * @return array{0: DateTime, 1: DateTime}
     * @throws DateMalformedStringException
     */
    private function currentWeekRange(): array
    {
        $start = new DateTime('monday this week');
        $start->setTime(0, 0);
        $end = (clone $start)->modify('+6 days');

        return [$start, $end];
    }
}
