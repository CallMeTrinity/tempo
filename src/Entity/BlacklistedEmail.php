<?php

namespace App\Entity;

use App\Repository\BlacklistedEmailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BlacklistedEmailRepository::class)]
#[ORM\Table(name: 'blacklisted_email')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà sur la liste noire.')]
class BlacklistedEmail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $blacklistedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $blacklistedBy = null;

    public function __construct(string $email, ?User $by = null, ?string $reason = null)
    {
        $this->email = mb_strtolower(trim($email));
        $this->blacklistedBy = $by;
        $this->reason = $reason;
        $this->blacklistedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getBlacklistedAt(): \DateTimeImmutable
    {
        return $this->blacklistedAt;
    }

    public function getBlacklistedBy(): ?User
    {
        return $this->blacklistedBy;
    }
}
