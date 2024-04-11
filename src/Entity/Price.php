<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: PriceRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TYPE_ISACTIVE', fields: ['type', 'isActive'])]
class Price
{
    use TimestampableEntity;

    public const TYPE_SINGLE       = 'single';
    public const TYPE_MONTHLY      = 'monthly';
    public const TYPE_TEN_VOUCHERS = '10-vouchers';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?int $amount = null;

    public static function getTypes(): array
    {
        return [
            self::TYPE_SINGLE       => self::TYPE_SINGLE,
            self::TYPE_MONTHLY      => self::TYPE_MONTHLY,
            self::TYPE_TEN_VOUCHERS => self::TYPE_TEN_VOUCHERS,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (false === \in_array($type, self::getTypes(), true)) {
            throw new \InvalidArgumentException('Invalid type');
        }

        $this->type = $type;

        return $this;
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
}
