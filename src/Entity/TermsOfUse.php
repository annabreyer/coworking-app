<?php

namespace App\Entity;

use App\Repository\TermsOfUseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TermsOfUseRepository::class)]
class TermsOfUse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $version = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\OneToMany(targetEntity: UserTermsOfUse::class, mappedBy: 'termsOfUse')]
    private Collection $userTermsOfUses;

    public function __construct()
    {
        $this->userTermsOfUses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return Collection<int, UserTermsOfUse>
     */
    public function getUserTermsOfUses(): Collection
    {
        return $this->userTermsOfUses;
    }

    public function addUserTermsOfUse(UserTermsOfUse $userTermsOfUse): static
    {
        if (!$this->userTermsOfUses->contains($userTermsOfUse)) {
            $this->userTermsOfUses->add($userTermsOfUse);
            $userTermsOfUse->setTermsOfUse($this);
        }

        return $this;
    }

    public function removeUserTermsOfUse(UserTermsOfUse $userTermsOfUse): static
    {
        if ($this->userTermsOfUses->removeElement($userTermsOfUse)) {
            // set the owning side to null (unless already changed)
            if ($userTermsOfUse->getTermsOfUse() === $this) {
                $userTermsOfUse->setTermsOfUse(null);
            }
        }

        return $this;
    }
}
