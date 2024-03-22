<?php

namespace App\Entity;

use App\Repository\UserTermsOfUseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTermsOfUseRepository::class)]
class UserTermsOfUse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'acceptedTermsOfUse')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userTermsOfUses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TermsOfUse $termsOfUse = null;

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

    public function getTermsOfUse(): ?TermsOfUse
    {
        return $this->termsOfUse;
    }

    public function setTermsOfUse(?TermsOfUse $termsOfUse): static
    {
        $this->termsOfUse = $termsOfUse;

        return $this;
    }
}
