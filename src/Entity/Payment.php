<?php

declare(strict_types = 1);

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
    public const PAYMENT_TYPE_PAYPAL      = 'paypal';
    public const PAYMENT_TYPE_REFUND      = 'refund';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private int $amount = 0;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    private Invoice $invoice;

    #[ORM\ManyToOne]
    private ?Voucher $voucher = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    /**
     * @return array<string>
     */
    public static function getPaymentTypes(): array
    {
        return [
            self::PAYMENT_TYPE_VOUCHER     => self::PAYMENT_TYPE_VOUCHER,
            self::PAYMENT_TYPE_TRANSACTION => self::PAYMENT_TYPE_TRANSACTION,
            self::PAYMENT_TYPE_PAYPAL      => self::PAYMENT_TYPE_PAYPAL,
            self::PAYMENT_TYPE_REFUND      => self::PAYMENT_TYPE_REFUND,
        ];
    }

    public function __construct(?string $type = null)
    {
        if (null === $type) {
            $this->type = '';

            return;
        }

        if (false === \in_array($type, self::getPaymentTypes(), true)) {
            throw new \InvalidArgumentException('Invalid payment type');
        }

        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): int
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

    public function setType(string $type): static
    {
        if (false === \in_array($type, self::getPaymentTypes(), true)) {
            throw new \InvalidArgumentException('Invalid payment type');
        }

        $this->type = $type;

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

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): static
    {
        $this->invoice = $invoice;
        $invoice->addPayment($this);

        return $this;
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

    public function isVoucherPayment(): bool
    {
        return self::PAYMENT_TYPE_VOUCHER === $this->type;
    }

    public function isTransactionPayment(): bool
    {
        return self::PAYMENT_TYPE_TRANSACTION === $this->type;
    }

    public function isPayPalPayment(): bool
    {
        return self::PAYMENT_TYPE_PAYPAL === $this->type;
    }

    public function __toString(): string
    {
        if (null === $this->getDate() || empty($this->getType())) {
            return '';
        }

        $type = $this->type === self::PAYMENT_TYPE_VOUCHER ? 'Voucher ' . $this->voucher->getCode() : $this->type;

        return $this->amount / 100 . ' € | ' . $this->getDate()->format('d.m.Y') . ' | ' . $type . ' | ' .($this->comment ? ' (' . $this->comment . ')' : '');
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }
}
