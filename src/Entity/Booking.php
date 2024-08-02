<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    use TimestampableEntity;
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
    #[ORM\JoinColumn(nullable: true)]
    private ?WorkStation $workStation = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(nullable: true)]
    private ?int $amount = null;

    #[ORM\Column()]
    private bool $isCancelled = false;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
    }

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

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function isFullyPaid(): bool
    {
        return null !== $this->invoice && $this->invoice->isFullyPaid();
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function __toString(): string
    {
        $roomName        = $this->room?->getName();
        $businessDayDate = $this->businessDay?->getDate();

        if (null === $businessDayDate || null === $roomName) {
            return '';
        }

        return $roomName . ' - ' . $businessDayDate->format('d.m.Y');
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    public function setIsCancelled(bool $isCancelled): static
    {
        $this->isCancelled = $isCancelled;

        return $this;
    }
}
