<?php

namespace App\Entity;

use App\Enum\ContractType;
use App\Enum\Roles;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'] )]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, TimeEntry>
     */
    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'user')]
    private Collection $timeEntries;

    #[ORM\Column(enumType: Roles::class)]
    private ?Roles $role = Roles::USER;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(nullable: true, enumType: ContractType::class)]
    private ?ContractType $contractType = null;

    #[ORM\Column(nullable: true)]
    private ?float $weeklyHours = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $contractStartDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column(options: ['default' => 5])]
    private ?int $workingDaysPerWeek = null;

    #[ORM\Column(nullable: true)]
    private ?array $defaultRemoteDays = null;

    public function __construct()
    {
        $this->timeEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

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

    /**
     * @return Collection<int, TimeEntry>
     */
    public function getTimeEntries(): Collection
    {
        return $this->timeEntries;
    }

    public function addTimeEntry(TimeEntry $timeEntry): static
    {
        if (!$this->timeEntries->contains($timeEntry)) {
            $this->timeEntries->add($timeEntry);
            $timeEntry->setUser($this);
        }

        return $this;
    }

    public function removeTimeEntry(TimeEntry $timeEntry): static
    {
        if ($this->timeEntries->removeElement($timeEntry)) {
            // set the owning side to null (unless already changed)
            if ($timeEntry->getUser() === $this) {
                $timeEntry->setUser(null);
            }
        }

        return $this;
    }

    public function getRole(): ?Roles
    {
        return $this->role;
    }

    public function setRole(Roles $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRoles(): array
    {
        return [$this->role->value];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getContractType(): ?ContractType
    {
        return $this->contractType;
    }

    public function setContractType(?ContractType $contractType): static
    {
        $this->contractType = $contractType;

        return $this;
    }

    public function getWeeklyHours(): ?float
    {
        return $this->weeklyHours;
    }

    public function setWeeklyHours(?float $weeklyHours): static
    {
        $this->weeklyHours = $weeklyHours;

        return $this;
    }

    public function getContractStartDate(): ?\DateTime
    {
        return $this->contractStartDate;
    }

    public function setContractStartDate(?\DateTime $contractStartDate): static
    {
        $this->contractStartDate = $contractStartDate;

        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }

    public function getFullName(): ?string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    public function getInitials(): string
    {
        if ($this->firstName || $this->lastName) {
            return strtoupper(mb_substr($this->firstName ?? '', 0, 1) . mb_substr($this->lastName ?? '', 0, 1));
        }

        return strtoupper(mb_substr($this->email ?? '?', 0, 2));
    }

    /**
     * Daily expected hours, assuming 5 working days per week.
     */
    public function getExpectedDailyHours(): ?float
    {
        return $this->weeklyHours !== null ? round($this->weeklyHours / $this->workingDaysPerWeek, 2) : null;
    }

    public function isProfileComplete(): bool
    {
        return $this->firstName !== null
            && $this->lastName !== null
            && $this->contractType !== null
            && $this->weeklyHours !== null
            && $this->contractStartDate !== null
            && $this->jobTitle !== null;
    }

    public function getWorkingDaysPerWeek(): ?int
    {
        return $this->workingDaysPerWeek;
    }

    public function setWorkingDaysPerWeek(int $workingDaysPerWeek): static
    {
        $this->workingDaysPerWeek = $workingDaysPerWeek;

        return $this;
    }

    public function getDefaultRemoteDays(): ?array
    {
        return $this->defaultRemoteDays;
    }

    public function setDefaultRemoteDays(?array $defaultRemoteDays): static
    {
        $this->defaultRemoteDays = $defaultRemoteDays;

        return $this;
    }

    public function isContractActive(\DateTimeInterface $date): bool
    {
        return $this->contractType !== null
            && $this->contractStartDate !== null
            && $this->contractStartDate <= $date;
    }
}
