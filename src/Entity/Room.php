<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
class Room
{
    use TimestampableEntity;
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

    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Booking::class, orphanRemoval: true)]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings     = new ArrayCollection();
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

    public function isOpen(): bool
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

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function __toString(): string
    {
        if (null === $this->name) {
            return '';
        }

        return $this->name;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (false === $this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setRoom($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getRoom() === $this) {
                $booking->setRoom(null);
            }
        }

        return $this;
    }
}
