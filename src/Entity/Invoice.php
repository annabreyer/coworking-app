<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\UniqueConstraint(fields: ['number'])]
class Invoice
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 15)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Booking::class, cascade: ['persist'])]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Payment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Voucher::class, cascade: ['persist'])]
    private Collection $vouchers;

    #[ORM\Column(type: 'uuid')]
    private Uuid $uuid;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payPalOrderId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    public function __construct()
    {
        $this->uuid     = Uuid::v7();
        $this->bookings = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->vouchers = new ArrayCollection();
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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

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
            $booking->setInvoice($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getInvoice() === $this) {
                $booking->setInvoice(null);
            }
        }

        return $this;
    }

    public function getFirstBooking(): ?Booking
    {
        if (0 === $this->bookings->count()) {
            return null;
        }

        return $this->bookings->first();
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
        if (false === $this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setInvoice($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        $this->payments->removeElement($payment);

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

    public function isFullyPaid(): bool
    {
        $paidAmount = 0;
        foreach ($this->payments as $payment) {
            $paidAmount += $payment->getAmount();
        }

        return $paidAmount >= $this->getAmount();
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
        if (false === $this->vouchers->contains($voucher)) {
            $this->vouchers->add($voucher);
            $voucher->setInvoice($this);
        }

        return $this;
    }

    public function removeVoucher(Voucher $voucher): static
    {
        if ($this->vouchers->removeElement($voucher)) {
            // set the owning side to null (unless already changed)
            if ($voucher->getInvoice() === $this) {
                $voucher->setInvoice(null);
            }
        }

        return $this;
    }

    public function setVouchers(Collection $vouchers): static
    {
        $this->vouchers = $vouchers;

        return $this;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getPayPalOrderId(): ?string
    {
        return $this->payPalOrderId;
    }

    public function setPayPalOrderId(?string $payPalOrderId): static
    {
        $this->payPalOrderId = $payPalOrderId;

        return $this;
    }

    public function isFullyPaidByVoucher(): bool
    {
        if (0 === $this->payments->count()) {
            return false;
        }

        if (false === $this->isFullyPaid()) {
            return false;
        }

        foreach ($this->payments as $payment) {
            if (\in_array($payment->getType(), [Payment::PAYMENT_TYPE_PAYPAL, Payment::PAYMENT_TYPE_TRANSACTION], true)) {
                return false;
            }
        }

        return true;
    }

    public function isFullyPaidByPayPal(): bool
    {
        if (0 === $this->payments->count()) {
            return false;
        }

        if (false === $this->isFullyPaid()) {
            return false;
        }

        foreach ($this->payments as $payment) {
            if (\in_array($payment->getType(), [Payment::PAYMENT_TYPE_VOUCHER, Payment::PAYMENT_TYPE_TRANSACTION], true)) {
                return false;
            }
        }

        return true;
    }

    public function isFullyPaidByTransaction(): bool
    {
        if (0 === $this->payments->count()) {
            return false;
        }

        if (false === $this->isFullyPaid()) {
            return false;
        }

        foreach ($this->payments as $payment) {
            if (\in_array($payment->getType(), [Payment::PAYMENT_TYPE_VOUCHER, Payment::PAYMENT_TYPE_PAYPAL], true)) {
                return false;
            }
        }

        return true;
    }

    public function isVoucherInvoice(): bool
    {
        return 0 < $this->vouchers->count();
    }

    public function isBookingInvoice(): bool
    {
        return 0 < $this->bookings->count();
    }

    public function __toString(): string
    {
        return $this->getNumber() ?? '';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isRefund(): bool
    {
        return 0 > $this->getAmount();
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        if ($this->isFullyPaid() && 0 === $this->payments->count()) {
            return $this->date;
        }

        return $this->payments->last()->getDate();
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getPaidAmount(): int
    {
        $paidAmount = 0;

        foreach ($this->payments as $payment) {
            $paidAmount += $payment->getAmount();
        }

        return $paidAmount;
    }

    public function getRemainingAmount(): int
    {
        return $this->getAmount() - $this->getPaidAmount();
    }
}
