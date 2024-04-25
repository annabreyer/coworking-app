<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VoucherRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Clock\ClockAwareTrait;

#[ORM\Entity(repositoryClass: VoucherRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CODE', fields: ['code'])]
class Voucher
{
    use ClockAwareTrait;
    use TimestampableEntity;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $value = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $useDate = null;

    #[ORM\ManyToOne(inversedBy: 'vouchers')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?VoucherType $voucherType = null;

    #[ORM\ManyToOne(inversedBy: 'vouchers')]
    private ?Invoice $invoice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getUseDate(): ?\DateTimeInterface
    {
        return $this->useDate;
    }

    public function setUseDate(?\DateTimeInterface $useDate): static
    {
        $this->useDate = $useDate;

        return $this;
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

    public function getVoucherType(): ?VoucherType
    {
        return $this->voucherType;
    }

    public function setVoucherType(?VoucherType $voucherType): static
    {
        $this->voucherType = $voucherType;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    /**
     * Invoice is set when the voucher is bought. It is not set when the voucher is used.
     */
    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiryDate < $this->now();
    }

    public function hasBeenPaid(): bool
    {
        if (null === $this->invoice) {
            return false;
        }

        if (0 === $this->invoice->getPayments()->count()) {
            return false;
        }

        return $this->invoice->isFullyPaid();
    }

    public function isValid(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if (null !== $this->useDate) {
            return false;
        }

        return $this->hasBeenPaid();
    }
}
