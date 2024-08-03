<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VoucherTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: VoucherTypeRepository::class)]
class VoucherType
{
    use TimestampableEntity;

    public const NAME_REFUND = 'refund';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $units = 0;

    #[ORM\Column(nullable: true)]
    private ?int $validityMonths = null;

    #[ORM\Column(nullable: true)]
    private ?int $unitaryValue = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\OneToMany(mappedBy: 'voucherType', targetEntity: Voucher::class)]
    private Collection $vouchers;

    #[ORM\OneToMany(mappedBy: 'voucherType', targetEntity: Price::class)]
    private Collection $prices;

    public function __construct()
    {
        $this->vouchers = new ArrayCollection();
        $this->prices   = new ArrayCollection();
    }

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

    public function getUnitaryValue(): ?int
    {
        return $this->unitaryValue;
    }

    public function setUnitaryValue(int $unitaryValue): static
    {
        $this->unitaryValue = $unitaryValue;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getVouchers(): Collection
    {
        return $this->vouchers;
    }

    public function setVouchers(Collection $vouchers): void
    {
        $this->vouchers = $vouchers;
    }

    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function setPrices(Collection $prices): void
    {
        $this->prices = $prices;
    }
}
