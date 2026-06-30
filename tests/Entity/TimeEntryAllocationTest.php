<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\TimeEntryProject;
use App\Enum\DayType;
use App\Enum\Status;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Couvre la cohérence des affectations projet (issue #8) via le validateur réel.
 * Aucune écriture en base : on ne fait que valider des entités en mémoire.
 */
class TimeEntryAllocationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    private function workedEntry(string $start, string $end, int $break): TimeEntry
    {
        return (new TimeEntry())
            ->setDayType(DayType::WORKED)
            ->setStatus(Status::DRAFT)
            ->setDate(new \DateTime('2026-06-15'))
            ->setStartTime(new \DateTime($start))
            ->setEndTime(new \DateTime($end))
            ->setBreakDuration($break)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
    }

    private function project(int $id): Project
    {
        $project = (new Project())->setName('P' . $id);
        $ref = new \ReflectionProperty(Project::class, 'id');
        $ref->setValue($project, $id);

        return $project;
    }

    private function allocation(Project $project, float $hours): TimeEntryProject
    {
        return (new TimeEntryProject())->setProject($project)->setHours($hours);
    }

    /** @return string[] propertyPath des violations */
    private function paths(TimeEntry $entry): array
    {
        return array_map(
            static fn ($v) => $v->getPropertyPath(),
            iterator_to_array($this->validator->validate($entry)),
        );
    }

    public function testNoAllocationsIsValid(): void
    {
        // 09:00 → 17:00 - 60min = 7h, sans projet : valide.
        $entry = $this->workedEntry('09:00', '17:00', 60);
        self::assertNotContains('projectAllocations', $this->paths($entry));
    }

    public function testAllocationUnderTotalIsValid(): void
    {
        $entry = $this->workedEntry('09:00', '17:00', 60); // 7h
        $entry->addProjectAllocation($this->allocation($this->project(1), 3));
        $entry->addProjectAllocation($this->allocation($this->project(2), 3.5));

        self::assertNotContains('projectAllocations', $this->paths($entry));
    }

    public function testAllocationExceedingTotalIsRejected(): void
    {
        $entry = $this->workedEntry('09:00', '17:00', 60); // 7h
        $entry->addProjectAllocation($this->allocation($this->project(1), 4));
        $entry->addProjectAllocation($this->allocation($this->project(2), 4));

        self::assertContains('projectAllocations', $this->paths($entry));
    }

    public function testDuplicateProjectIsRejected(): void
    {
        $entry = $this->workedEntry('09:00', '17:00', 60); // 7h
        $shared = $this->project(1);
        $entry->addProjectAllocation($this->allocation($shared, 1));
        // Même projet, autre instance même id.
        $entry->addProjectAllocation($this->allocation($this->project(1), 1));

        self::assertContains('projectAllocations', $this->paths($entry));
    }

    public function testHoursBelowMinimumIsRejected(): void
    {
        $entry = $this->workedEntry('09:00', '17:00', 60);
        $entry->addProjectAllocation($this->allocation($this->project(1), 0.25));

        $paths = $this->paths($entry);
        self::assertNotEmpty(array_filter(
            $paths,
            static fn (string $p) => str_contains($p, 'hours'),
        ));
    }
}
