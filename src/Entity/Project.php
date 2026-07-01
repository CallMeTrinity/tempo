<?php

namespace App\Entity;

use App\Enum\ProjectScope;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(enumType: ProjectScope::class)]
    private ?ProjectScope $scope = null;

    #[ORM\Column(length: 255)]
    private ?string $icon = null;

    #[ORM\Column(length: 63, nullable: true)]
    private ?string $color = null;

    #[ORM\ManyToOne(inversedBy: 'projects_owned')]
    private ?User $owner = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects_member')]
    private Collection $members;

    /**
     * @var Collection<int, TimeEntryProject>
     */
    #[ORM\OneToMany(targetEntity: TimeEntryProject::class, mappedBy: 'project')]
    private Collection $timeEntryProjects;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->timeEntryProjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getScope(): ?ProjectScope
    {
        return $this->scope;
    }

    public function setScope(ProjectScope $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }

        return $this;
    }

    public function removeMember(User $member): static
    {
        $this->members->removeElement($member);

        return $this;
    }

    public function isVisibleFor(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return match ($this->scope) {
            ProjectScope::PERSONAL => $this->owner === $user,
            ProjectScope::TEAM => $this->members->contains($user),
            null => false,
        };
    }

    /**
     * @return Collection<int, TimeEntryProject>
     */
    public function getTimeEntryProjects(): Collection
    {
        return $this->timeEntryProjects;
    }

    public function addTimeEntryProject(TimeEntryProject $timeEntryProject): static
    {
        if (!$this->timeEntryProjects->contains($timeEntryProject)) {
            $this->timeEntryProjects->add($timeEntryProject);
            $timeEntryProject->setProject($this);
        }

        return $this;
    }

    public function removeTimeEntryProject(TimeEntryProject $timeEntryProject): static
    {
        if ($this->timeEntryProjects->removeElement($timeEntryProject)) {
            // set the owning side to null (unless already changed)
            if ($timeEntryProject->getProject() === $this) {
                $timeEntryProject->setProject(null);
            }
        }

        return $this;
    }
}
