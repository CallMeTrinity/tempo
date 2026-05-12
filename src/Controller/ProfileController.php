<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Form\UserProfileType;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em, TimeEntryRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/profile.html.twig', [
            'user' => $user,
            'userProfileForm' => $form,
            'stats' => $this->computeStats($user, $repo),
        ]);
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
}
