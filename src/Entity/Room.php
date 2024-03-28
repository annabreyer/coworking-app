<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $isOpen = true;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: WorkStation::class)]
    private Collection $workStations;

    public function __construct()
    {
        $this->workStations = new ArrayCollection();
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

    public function isIsOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    /**
     * @return Collection<int, WorkStation>
     */
    public function getWorkStations(): Collection
    {
        return $this->workStations;
    }

    public function addWorkStation(WorkStation $workStation): static
    {
        if (!$this->workStations->contains($workStation)) {
            $this->workStations->add($workStation);
            $workStation->setRoom($this);
        }

        return $this;
    }

    public function removeWorkStation(WorkStation $workStation): static
    {
        if ($this->workStations->removeElement($workStation)) {
            // set the owning side to null (unless already changed)
            if ($workStation->getRoom() === $this) {
                $workStation->setRoom(null);
            }
        }

        return $this;
    }
}
