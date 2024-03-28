<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BusinessDay $businessDay = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkStation $workStation = null;

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

    public function getBusinessDay(): ?BusinessDay
    {
        return $this->businessDay;
    }

    public function setBusinessDay(?BusinessDay $businessDay): static
    {
        $this->businessDay = $businessDay;

        return $this;
    }

    public function getWorkStation(): ?WorkStation
    {
        return $this->workStation;
    }

    public function setWorkStation(?WorkStation $workStation): static
    {
        $this->workStation = $workStation;

        return $this;
    }
}
