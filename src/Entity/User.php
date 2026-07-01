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

    #[ORM\Column(length: 255, unique: true)]
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

    /**
     * Vérification de l'adresse email (clic sur le lien reçu par mail).
     * À distinguer de $isVerified, qui représente la validation par un admin.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isEmailVerified = false;

    /**
     * Dernier envoi d'un email de confirmation. Sert à imposer un délai (5 min)
     * avant de pouvoir en redemander un.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastVerificationEmailSentAt = null;

    /**
     * Utilisateur indépendant (auto-suivi) : ses heures ne passent plus par la
     * validation admin, elles sont enregistrées directement en SELF_TRACKED.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isIndependent = false;

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
    private int $workingDaysPerWeek = 5;

    #[ORM\Column(type: Types::JSON)]
    private array $defaultRemoteDays = [];

    #[ORM\Column(options: ['default' => 60])]
    private int $defaultBreakMinutes = 60;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'owner')]
    private Collection $projects_owned;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'members')]
    private Collection $projects_member;

    public function __construct()
    {
        $this->timeEntries = new ArrayCollection();
        $this->projects_owned = new ArrayCollection();
        $this->projects_member = new ArrayCollection();
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

    public function isAdmin(): bool
    {
        return $this->role === Roles::ADMIN;
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

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    /**
     * Délai imposé entre deux envois d'email de confirmation.
     */
    public const VERIFICATION_EMAIL_COOLDOWN = 300;

    public function getLastVerificationEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->lastVerificationEmailSentAt;
    }

    public function setLastVerificationEmailSentAt(?\DateTimeImmutable $lastVerificationEmailSentAt): static
    {
        $this->lastVerificationEmailSentAt = $lastVerificationEmailSentAt;

        return $this;
    }

    /**
     * Nombre de secondes restant avant de pouvoir renvoyer un email de
     * confirmation (0 si le délai est écoulé ou si aucun email n'a été envoyé).
     */
    public function getVerificationEmailCooldownRemaining(): int
    {
        if (null === $this->lastVerificationEmailSentAt) {
            return 0;
        }

        $elapsed = time() - $this->lastVerificationEmailSentAt->getTimestamp();

        return max(0, self::VERIFICATION_EMAIL_COOLDOWN - $elapsed);
    }

    public function isIndependent(): bool
    {
        return $this->isIndependent;
    }

    public function setIsIndependent(bool $isIndependent): static
    {
        $this->isIndependent = $isIndependent;

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
     * Heures journalières attendues = weeklyHours / workingDaysPerWeek.
     */
    public function getExpectedDailyHours(): ?float
    {
        if ($this->weeklyHours === null || $this->workingDaysPerWeek <= 0) {
            return null;
        }

        return round($this->weeklyHours / $this->workingDaysPerWeek, 2);
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

    public function getWorkingDaysPerWeek(): int
    {
        return $this->workingDaysPerWeek;
    }

    public function setWorkingDaysPerWeek(int $workingDaysPerWeek): static
    {
        $this->workingDaysPerWeek = $workingDaysPerWeek;

        return $this;
    }

    /**
     * @return int[] Liste de iso-weekdays (1=lundi … 7=dimanche).
     */
    public function getDefaultRemoteDays(): array
    {
        return $this->defaultRemoteDays;
    }

    /**
     * @param int[]|null $defaultRemoteDays
     */
    public function setDefaultRemoteDays(?array $defaultRemoteDays): static
    {
        $clean = [];
        foreach ($defaultRemoteDays ?? [] as $d) {
            $i = (int) $d;
            if ($i >= 1 && $i <= 7) {
                $clean[$i] = $i;
            }
        }
        sort($clean);
        $this->defaultRemoteDays = $clean;

        return $this;
    }

    public function isContractActive(\DateTimeInterface $date): bool
    {
        return $this->contractStartDate !== null
            && $this->contractStartDate <= $date;
    }

    public function getDefaultBreakMinutes(): int
    {
        return $this->defaultBreakMinutes;
    }

    public function setDefaultBreakMinutes(int $defaultBreakMinutes): static
    {
        $this->defaultBreakMinutes = max(0, $defaultBreakMinutes);

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjectsOwned(): Collection
    {
        return $this->projects_owned;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects_owned->contains($project)) {
            $this->projects_owned->add($project);
            $project->setOwner($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects_owned->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getOwner() === $this) {
                $project->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjectsMember(): Collection
    {
        return $this->projects_member;
    }

    public function addProjectsMember(Project $projectsMember): static
    {
        if (!$this->projects_member->contains($projectsMember)) {
            $this->projects_member->add($projectsMember);
            $projectsMember->addMember($this);
        }

        return $this;
    }

    public function removeProjectsMember(Project $projectsMember): static
    {
        if ($this->projects_member->removeElement($projectsMember)) {
            $projectsMember->removeMember($this);
        }

        return $this;
    }
}
