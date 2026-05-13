<?php

namespace App\Service;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\DayType;
use App\Repository\TimeEntryRepository;

/**
 * Logique métier de la fiche horaire : vue semaine + statistiques.
 *
 * Convention : un utilisateur travaille du lundi au (workingDaysPerWeek)e jour
 * de la semaine inclus. Ex : 5 = lun-ven, 6 = lun-sam, 7 = lun-dim.
 */
class TimesheetService
{
    public function __construct(private readonly TimeEntryRepository $entries)
    {
    }

    /**
     * Construit la grille hebdomadaire (7 cellules, du lundi de $weekStart).
     *
     * Chaque cellule contient :
     *   - date              : \DateTimeImmutable (00:00)
     *   - isoWeekday        : int 1..7
     *   - entry             : ?TimeEntry         entrée réelle persistée
     *   - virtualEntry      : ?TimeEntry         suggestion non persistée (predefined remote)
     *   - isWorkingDay      : bool
     *   - isPredefinedRemote: bool
     *   - isFuture          : bool
     *   - isToday           : bool
     *   - isBeforeContract  : bool
     *
     * @return list<array{
     *   date: \DateTimeImmutable,
     *   isoWeekday: int,
     *   entry: ?TimeEntry,
     *   virtualEntry: ?TimeEntry,
     *   isWorkingDay: bool,
     *   isPredefinedRemote: bool,
     *   isFuture: bool,
     *   isToday: bool,
     *   isBeforeContract: bool,
     * }>
     */
    public function buildWeekView(User $user, \DateTimeInterface $weekStart): array
    {
        $monday = $this->normalizeMonday($weekStart);
        $sunday = $monday->modify('+6 days');
        $today = new \DateTimeImmutable('today');
        $contractStart = $user->getContractStartDate();
        $contractStartImm = $contractStart !== null
            ? \DateTimeImmutable::createFromMutable($contractStart)->setTime(0, 0)
            : null;

        $remoteDays = array_flip($user->getDefaultRemoteDays());
        $workingCount = max(0, min(7, $user->getWorkingDaysPerWeek()));

        $byKey = [];
        foreach ($this->entries->findByUserBetween(
            $user,
            \DateTime::createFromImmutable($monday),
            \DateTime::createFromImmutable($sunday)
        ) as $e) {
            $byKey[$e->getDate()->format('Y-m-d')] = $e;
        }

        $cells = [];
        for ($i = 0; $i < 7; ++$i) {
            $date = $monday->modify("+$i days");
            $key = $date->format('Y-m-d');
            $isoWd = (int) $date->format('N');
            $entry = $byKey[$key] ?? null;

            $isWorkingDay = $isoWd <= $workingCount;
            $isPredefinedRemote = isset($remoteDays[$isoWd]);
            $isFuture = $date > $today;
            $isToday = $date == $today;
            $isBeforeContract = $contractStartImm !== null && $date < $contractStartImm;

            $virtual = null;
            if ($entry === null && $isPredefinedRemote && !$isBeforeContract) {
                $virtual = (new TimeEntry())
                    ->setUser($user)
                    ->setDate(\DateTime::createFromImmutable($date))
                    ->setDayType(DayType::REMOTE);
            }

            $cells[] = [
                'date' => $date,
                'isoWeekday' => $isoWd,
                'entry' => $entry,
                'virtualEntry' => $virtual,
                'isWorkingDay' => $isWorkingDay,
                'isPredefinedRemote' => $isPredefinedRemote,
                'isFuture' => $isFuture,
                'isToday' => $isToday,
                'isBeforeContract' => $isBeforeContract,
            ];
        }

        return $cells;
    }

    /**
     * @param TimeEntry[] $entries entrées de la semaine
     *
     * @return array{
     *   workedHours: float,
     *   overtimeHours: float,
     *   deficitHours: float,
     *   daysWorked: int,
     *   weeklyTarget: ?float,
     *   progress: ?int,
     * }
     */
    public function computeWeeklyStats(User $user, array $entries): array
    {
        $worked = 0.0;
        $daysWorked = 0;
        foreach ($entries as $entry) {
            $worked += $entry->getHoursWorked();
            if ($entry->getDayType()->isProductive()) {
                ++$daysWorked;
            }
        }
        $worked = round($worked, 2);
        $target = $user->getWeeklyHours();

        $overtime = $target !== null ? max(0.0, round($worked - $target, 2)) : 0.0;
        $deficit = $target !== null ? max(0.0, round($target - $worked, 2)) : 0.0;
        $progress = ($target !== null && $target > 0)
            ? min(100, (int) round(($worked / $target) * 100))
            : null;

        return [
            'workedHours' => $worked,
            'overtimeHours' => $overtime,
            'deficitHours' => $deficit,
            'daysWorked' => $daysWorked,
            'weeklyTarget' => $target,
            'progress' => $progress,
        ];
    }

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   totalHours: float,
     *   daysFilled: int,
     *   expectedWorkingDays: int,
     *   expectedHours: float,
     *   overtimeHours: float,
     *   deficitHours: float,
     *   entries: TimeEntry[],
     * }
     */
    public function computeMonthlyStats(User $user, int $year, int $month): array
    {
        $entries = $this->entries->findByUserForMonth($user, $year, $month);

        $total = 0.0;
        $daysFilled = 0;
        foreach ($entries as $entry) {
            $total += $entry->getHoursWorked();
            if ($entry->getDayType()->isProductive()) {
                ++$daysFilled;
            }
        }
        $total = round($total, 2);

        $expectedDays = $this->countWorkingDaysInMonth($user, $year, $month);
        $expectedHours = round($expectedDays * ($user->getExpectedDailyHours() ?? 0.0), 2);

        $overtime = round(max(0.0, $total - $expectedHours), 2);
        $deficit = round(max(0.0, $expectedHours - $total), 2);

        return [
            'year' => $year,
            'month' => $month,
            'totalHours' => $total,
            'daysFilled' => $daysFilled,
            'expectedWorkingDays' => $expectedDays,
            'expectedHours' => $expectedHours,
            'overtimeHours' => $overtime,
            'deficitHours' => $deficit,
            'entries' => $entries,
        ];
    }

    /**
     * Lundi (00:00) de la semaine de la date donnée.
     */
    public function normalizeMonday(\DateTimeInterface $date): \DateTimeImmutable
    {
        $imm = $date instanceof \DateTimeImmutable
            ? $date
            : \DateTimeImmutable::createFromInterface($date);
        $imm = $imm->setTime(0, 0);
        $iso = (int) $imm->format('N');

        return $iso === 1 ? $imm : $imm->modify('-' . ($iso - 1) . ' days');
    }

    /**
     * Nombre de jours ouvrés contractuels dans le mois, en tenant compte de la
     * date de début de contrat.
     */
    private function countWorkingDaysInMonth(User $user, int $year, int $month): int
    {
        $workingCount = max(0, min(7, $user->getWorkingDaysPerWeek()));
        if ($workingCount === 0) {
            return 0;
        }

        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        $contractStart = $user->getContractStartDate();
        $contractStartImm = $contractStart !== null
            ? \DateTimeImmutable::createFromMutable($contractStart)->setTime(0, 0)
            : null;

        $count = 0;
        for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
            if ($contractStartImm !== null && $d < $contractStartImm) {
                continue;
            }
            if ((int) $d->format('N') <= $workingCount) {
                ++$count;
            }
        }

        return $count;
    }
}
