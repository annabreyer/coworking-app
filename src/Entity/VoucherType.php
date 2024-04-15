<?php

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

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $units = null;

    #[ORM\Column(nullable: true)]
    private ?int $validityMonths = null;

    /**
     * @var Collection<int, Price>
     */
    #[ORM\OneToMany(mappedBy: 'voucherType', targetEntity: Price::class)]
    private Collection $prices;

    /**
     * @var Collection<int, Voucher>
     */
    #[ORM\OneToMany(mappedBy: 'voucherType', targetEntity: Voucher::class)]
    private Collection $vouchers;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
        $this->vouchers = new ArrayCollection();
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

    /**
     * @return Collection<int, Price>
     */
    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function addPrice(Price $price): static
    {
        if (false === $this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setVoucherType($this);
        }

        return $this;
    }

    public function removePrice(Price $price): static
    {
        if ($this->prices->removeElement($price)) {
            // set the owning side to null (unless already changed)
            if ($price->getVoucherType() === $this) {
                $price->setVoucherType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Voucher>
     */
    public function getVouchers(): Collection
    {
        return $this->vouchers;
    }

    public function addVoucher(Voucher $voucher): static
    {
        if (!$this->vouchers->contains($voucher)) {
            $this->vouchers->add($voucher);
            $voucher->setVoucherType($this);
        }

        return $this;
    }

    public function removeVoucher(Voucher $voucher): static
    {
        if ($this->vouchers->removeElement($voucher)) {
            // set the owning side to null (unless already changed)
            if ($voucher->getVoucherType() === $this) {
                $voucher->setVoucherType(null);
            }
        }

        return $this;
    }
}
