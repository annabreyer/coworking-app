<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BusinessDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: BusinessDayRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_DATE', fields: ['date'])]
class BusinessDay
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $date;

    #[ORM\Column]
    private bool $isOpen = true;

    #[ORM\OneToMany(mappedBy: 'businessDay', targetEntity: Booking::class, orphanRemoval: true)]
    private Collection $bookings;

    public function __construct(\DateTimeInterface $date)
    {
        $this->date     = $date;
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
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
            $booking->setBusinessDay($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getBusinessDay() === $this) {
                $booking->setBusinessDay(null);
            }
        }

        return $this;
    }

    public function getWeekDayLong(): string
    {
        return $this->date->format('l');
    }

    public function getWeekDayShort(): string
    {
        return $this->date->format('D');
    }

    public function getBookingsForRoom(Room $room): Collection
    {
        return $this->bookings->filter(
            static fn (Booking $booking) => $booking->getRoom() === $room
        );
    }

    public function __toString(): string
    {
        return $this->date->format('Y-m-d');
    }
}
