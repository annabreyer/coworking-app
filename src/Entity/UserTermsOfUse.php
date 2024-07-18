<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserTermsOfUseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: UserTermsOfUseRepository::class)]
class UserTermsOfUse
{
    use TimestampableEntity;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'acceptedTermsOfUse')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'userTermsOfUses')]
    #[ORM\JoinColumn(nullable: false)]
    private TermsOfUse $termsOfUse;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $acceptedOn = null;

    public function __construct(User $user, TermsOfUse $termsOfUse)
    {
        $this->user       = $user;
        $this->termsOfUse = $termsOfUse;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getTermsOfUse(): ?TermsOfUse
    {
        return $this->termsOfUse;
    }

    public function getAcceptedOn(): ?\DateTimeInterface
    {
        return $this->acceptedOn;
    }

    public function setAcceptedOn(\DateTimeInterface $acceptedOn): static
    {
        $this->acceptedOn = $acceptedOn;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getTermsOfUse()->getVersion() . ' | ' . $this->getAcceptedOn()->format('d.m.Y');
    }
}
