<?php

namespace App\Entity;

use App\Repository\TimeEntryProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TimeEntryProjectRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_time_entry_project', columns: ['time_entry_id', 'project_id'])]
class TimeEntryProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projectAllocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TimeEntry $timeEntry = null;

    #[ORM\ManyToOne(inversedBy: 'timeEntryProjects')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Sélectionnez un projet.')]
    private ?Project $project = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez un nombre d’heures.')]
    #[Assert\GreaterThanOrEqual(value: 0.5, message: 'Minimum 0,5 h par projet.')]
    private ?float $hours = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimeEntry(): ?TimeEntry
    {
        return $this->timeEntry;
    }

    public function setTimeEntry(?TimeEntry $timeEntry): static
    {
        $this->timeEntry = $timeEntry;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getHours(): ?float
    {
        return $this->hours;
    }

    public function setHours(float $hours): static
    {
        $this->hours = $hours;

        return $this;
    }
}
