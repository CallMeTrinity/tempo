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

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
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
        $overwritten = 0;
        $skippedWorked = 0;
        $skippedLocked = 0;
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
                $existing = $existingByKey[$key];
                // Règle d'écrasement :
                //  - WORKED : on préserve toujours (heures réelles)
                //  - status verrouillé (SUBMITTED/APPROVED) : on préserve
                //  - sinon (DRAFT/TO_BE_REVIEWED en non-WORKED) : on remplace
                if ($existing->getDayType() === DayType::WORKED) {
                    ++$skippedWorked;
                } elseif (!$existing->getStatus()->isEditableByUser()) {
                    ++$skippedLocked;
                } else {
                    $existing
                        ->setDayType($type)
                        ->setStartTime(null)
                        ->setEndTime(null)
                        ->setBreakDuration(null)
                        ->setNote($note)
                        ->setUpdatedAt(new \DateTimeImmutable());
                    ++$overwritten;
                }
            } else {
                $entry = (new TimeEntry())
                    ->setUser($user)
                    ->setDate(clone $cursor)
                    ->setStatus($user->isIndependent() ? Status::SELF_TRACKED : Status::DRAFT)
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

        $touched = $created + $overwritten;
        $parts = [];
        $parts[] = sprintf('%d jour%s planifié%s en %s', $touched, $touched > 1 ? 's' : '', $touched > 1 ? 's' : '', $type->getLabel());

        $details = [];
        if ($overwritten > 0) {
            $details[] = $overwritten . ' remplacé' . ($overwritten > 1 ? 's' : '');
        }
        if ($skippedWorked > 0) {
            $details[] = $skippedWorked . ' jour' . ($skippedWorked > 1 ? 's' : '') . ' travaillé' . ($skippedWorked > 1 ? 's' : '') . ' conservé' . ($skippedWorked > 1 ? 's' : '');
        }
        if ($skippedLocked > 0) {
            $details[] = $skippedLocked . ' soumis/approuvé' . ($skippedLocked > 1 ? 's' : '') . ' conservé' . ($skippedLocked > 1 ? 's' : '');
        }
        if ($skippedWeekend > 0) {
            $details[] = $skippedWeekend . ' week-end' . ($skippedWeekend > 1 ? 's' : '');
        }
        if ($skippedBeforeContract > 0) {
            $details[] = $skippedBeforeContract . ' avant contrat';
        }
        if ($details !== []) {
            $parts[] = '(' . implode(', ', $details) . ')';
        }

        $this->addFlash($touched > 0 ? 'success' : 'info', implode(' ', $parts) . '.');

        return $redirect;
    }
}
