<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\DayType;
use App\Enum\Status;
use App\Form\DayPlanningType;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'app_planning_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        TimeEntryRepository $repo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(DayPlanningType::class);
        $form->handleRequest($request);

        $redirect = $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Planification invalide. Vérifiez les dates et le type.');
            return $redirect;
        }

        $data = $form->getData();
        /** @var \DateTime $start */
        $start = $data['startDate'];
        /** @var \DateTime $end */
        $end = $data['endDate'];
        /** @var DayType $type */
        $type = $data['dayType'];
        $note = $data['note'] ?? null;

        if ($end < $start) {
            $this->addFlash('error', 'La date de fin doit être postérieure ou égale à la date de début.');
            return $redirect;
        }

        $contractStart = $user->getContractStartDate();
        $existingByKey = [];
        foreach ($repo->findByUserBetween($user, $start, $end) as $e) {
            $existingByKey[$e->getDate()->format('Y-m-d')] = $e;
        }

        $created = 0;
        $skippedExisting = 0;
        $skippedWeekend = 0;
        $skippedBeforeContract = 0;

        $cursor = (clone $start)->setTime(0, 0);
        $stopAt = (clone $end)->setTime(0, 0);

        while ($cursor <= $stopAt) {
            $key = $cursor->format('Y-m-d');
            $iso = (int) $cursor->format('N');

            if ($iso > 5) {
                ++$skippedWeekend;
            } elseif ($contractStart !== null && $cursor < $contractStart) {
                ++$skippedBeforeContract;
            } elseif (isset($existingByKey[$key])) {
                ++$skippedExisting;
            } else {
                $entry = (new TimeEntry())
                    ->setUser($user)
                    ->setDate(clone $cursor)
                    ->setStatus(Status::DRAFT)
                    ->setDayType($type)
                    ->setNote($note)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUpdatedAt(new \DateTimeImmutable());
                $em->persist($entry);
                ++$created;
            }

            $cursor->modify('+1 day');
        }

        $em->flush();

        $parts = [];
        $parts[] = sprintf('%d jour%s planifié%s en %s', $created, $created > 1 ? 's' : '', $created > 1 ? 's' : '', $type->getLabel());
        $details = [];
        if ($skippedExisting > 0) {
            $details[] = $skippedExisting . ' déjà saisi' . ($skippedExisting > 1 ? 's' : '');
        }
        if ($skippedWeekend > 0) {
            $details[] = $skippedWeekend . ' week-end' . ($skippedWeekend > 1 ? 's' : '');
        }
        if ($skippedBeforeContract > 0) {
            $details[] = $skippedBeforeContract . ' avant contrat';
        }
        if ($details !== []) {
            $parts[] = '(ignorés : ' . implode(', ', $details) . ')';
        }

        $this->addFlash($created > 0 ? 'success' : 'info', implode(' ', $parts) . '.');

        return $redirect;
    }
}
