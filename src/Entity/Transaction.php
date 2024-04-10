<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    use TimestampableEntity;
    public const TRANSACTION_TYPE_BANK_TRANSFER = 'bank_transfer';
    public const TRANSACTION_TYPE_PAYPAL        = 'paypal';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: Payment::class)]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
    }

    /**
     * @return array<string>
     */
    public static function getTransactionTypes(): array
    {
        return [
            self::TRANSACTION_TYPE_BANK_TRANSFER => self::TRANSACTION_TYPE_BANK_TRANSFER,
            self::TRANSACTION_TYPE_PAYPAL        => self::TRANSACTION_TYPE_PAYPAL,
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
        if (false === \in_array($type, self::getTransactionTypes(), true)) {
            throw new \InvalidArgumentException('Invalid transaction type');
        }

        $this->type = $type;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setTransaction($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getTransaction() === $this) {
                $payment->setTransaction(null);
            }
        }

        return $this;
    }
}
