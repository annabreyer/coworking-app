<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    use TimestampableEntity;

    public const PAYMENT_TYPE_VOUCHER     = 'voucher';
    public const PAYMENT_TYPE_TRANSACTION = 'transaction';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    private Invoice $invoice;

    #[ORM\ManyToOne]
    private ?Voucher $voucher = null;

    #[ORM\ManyToOne]
    private ?Transaction $transaction = null;

    /**
     * @return array<string>
     */
    public static function getPaymentTypes(): array
    {
        return [
            self::PAYMENT_TYPE_VOUCHER     => self::PAYMENT_TYPE_VOUCHER,
            self::PAYMENT_TYPE_TRANSACTION => self::PAYMENT_TYPE_TRANSACTION,
        ];
    }

    public function __construct(Invoice $invoice, string $type)
    {
        if (false === \in_array($type, self::getPaymentTypes(), true)) {
            throw new \InvalidArgumentException('Invalid payment type');
        }
        $this->type    = $type;
        $this->invoice = $invoice;
        $invoice->addPayment($this);
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): string
    {
        return $this->type;
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

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getVoucher(): ?Voucher
    {
        return $this->voucher;
    }

    public function setVoucher(?Voucher $voucher): static
    {
        $this->voucher = $voucher;

        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function isVoucherPayment(): bool
    {
        return self::PAYMENT_TYPE_VOUCHER === $this->type;
    }

    public function isTransactionPayment(): bool
    {
        return self::PAYMENT_TYPE_TRANSACTION === $this->type;
    }
}
