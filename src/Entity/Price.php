<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: PriceRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ISUNITARY_ISACTIVE', fields: ['isUnitary', 'isActive'])]
class Price
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column]
    private bool $isUnitary = false;

    #[ORM\Column]
    private bool $isVoucher = false;

    #[ORM\Column]
    private bool $isSubscription = false;

    #[ORM\ManyToOne(inversedBy: 'prices')]
    private ?VoucherType $voucherType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function isUnitary(): ?bool
    {
        return $this->isUnitary;
    }

    public function setIsUnitary(bool $isUnitary): static
    {
        $this->isUnitary      = $isUnitary;
        $this->isVoucher      = false;
        $this->isSubscription = false;

        return $this;
    }

    public function isVoucher(): ?bool
    {
        return $this->isVoucher;
    }

    public function setIsVoucher(bool $isVoucher): static
    {
        $this->isVoucher      = $isVoucher;
        $this->isUnitary      = false;
        $this->isSubscription = false;

        return $this;
    }

    public function isSubscription(): ?bool
    {
        return $this->isSubscription;
    }

    public function setIsSubscription(bool $isSubscription): static
    {
        $this->isSubscription = $isSubscription;
        $this->isUnitary      = false;
        $this->isVoucher      = false;

        return $this;
    }

    public function getVoucherType(): ?VoucherType
    {
        return $this->voucherType;
    }

    public function setVoucherType(?VoucherType $voucherType): static
    {
        $this->voucherType = $voucherType;
        $this->setIsVoucher(true);

        return $this;
    }
}
