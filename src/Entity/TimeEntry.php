<?php

namespace App\Entity;

use App\Enum\DayType;
use App\Enum\Status;
use App\Repository\TimeEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TimeEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_date', columns: ['user_id', 'date'])]
#[UniqueEntity(fields: ['user', 'date'], message: 'Une entrée existe déjà pour cette journée.')]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'timeEntries')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $endTime = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Pause en minutes'])]
    #[Assert\PositiveOrZero(message: 'La pause doit être positive ou nulle.')]
    private ?int $breakDuration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(nullable: true, enumType: DayType::class)]
    private ?DayType $dayType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStartTime(): ?\DateTime
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTime $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTime $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getBreakDuration(): ?int
    {
        return $this->breakDuration;
    }

    public function setBreakDuration(?int $breakDuration): static
    {
        $this->breakDuration = $breakDuration;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Durée travaillée en heures décimales (ex: 7.5 = 7h30).
     * breakDuration est exprimé en minutes.
     */
    public function getHoursWorked(): float
    {
        if ($this->startTime === null || $this->endTime === null) {
            return 0.0;
        }
        switch ($this->dayType) {
            case DayType::WORKED:
                $diffSeconds = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
                $hours = $diffSeconds / 3600;
                $breakHours = ($this->breakDuration ?? 0) / 60;

                return max(0, round($hours - $breakHours, 2));
            case DayType::REMOTE or DayType::PTO:
                return $this->user->getExpectedDailyHours() ?? 0.0;
            default:
                return 0.0;
        }
    }

    public function getDayType(): ?DayType
    {
        return $this->dayType;
    }

    public function setDayType(?DayType $dayType): static
    {
        $this->dayType = $dayType;

        return $this;
    }
}
