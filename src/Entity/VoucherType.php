<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VoucherTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: VoucherTypeRepository::class)]
class VoucherType
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $units = 0;

    #[ORM\Column(nullable: true)]
    private ?int $validityMonths = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnits(): ?int
    {
        return $this->units;
    }

    public function setUnits(int $units): static
    {
        $this->units = $units;

        return $this;
    }

    public function getValidityMonths(): ?int
    {
        return $this->validityMonths;
    }

    public function setValidityMonths(?int $validityMonths): static
    {
        $this->validityMonths = $validityMonths;

        return $this;
    }
}
