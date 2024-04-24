<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.unique')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableEntity;

    #[Groups(['admin_action'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['admin_action'])]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[Groups(['admin_action'])]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[Groups(['admin_action'])]
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthdate = null;

    #[Groups(['admin_action'])]
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[Groups(['admin_action'])]
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acceptedCodeOfConduct = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acceptedDataProtection = null;

    #[Groups(['admin_action'])]
    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[Groups(['admin_action'])]
    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserTermsOfUse::class, cascade: ['persist', 'remove'])]
    private Collection $acceptedTermsOfUse;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ResetPasswordRequest::class, cascade: ['persist', 'remove'])]
    private Collection $resetPasswordRequests;

    #[Groups(['admin_action'])]
    #[ORM\Column(type: 'string')]
    private ?string $mobilePhone = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserAction::class)]
    private Collection $userActions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AdminAction::class)]
    private Collection $adminActions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Booking::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Voucher::class)]
    private Collection $vouchers;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    public function __construct()
    {
        $this->acceptedTermsOfUse    = new ArrayCollection();
        $this->resetPasswordRequests = new ArrayCollection();
        $this->userActions           = new ArrayCollection();
        $this->adminActions          = new ArrayCollection();
        $this->bookings              = new ArrayCollection();
        $this->invoices              = new ArrayCollection();
        $this->vouchers              = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, UserTermsOfUse>
     */
    public function getAcceptedTermsOfUse(): Collection
    {
        return $this->acceptedTermsOfUse;
    }

    public function addAcceptedTermsOfUse(UserTermsOfUse $acceptedTermsOfUse): static
    {
        if (
            false    === $this->acceptedTermsOfUse->contains($acceptedTermsOfUse)
            && $this === $acceptedTermsOfUse->getUser()
        ) {
            $this->acceptedTermsOfUse->add($acceptedTermsOfUse);
        }

        return $this;
    }

    public function removeAcceptedTermsOfUse(UserTermsOfUse $acceptedTermsOfUse): static
    {
        $this->acceptedTermsOfUse->removeElement($acceptedTermsOfUse);

        return $this;
    }

    public function getAcceptedCodeOfConduct(): ?\DateTimeInterface
    {
        return $this->acceptedCodeOfConduct;
    }

    public function setAcceptedCodeOfConduct(?\DateTimeInterface $acceptedCodeOfConduct): static
    {
        $this->acceptedCodeOfConduct = $acceptedCodeOfConduct;

        return $this;
    }

    public function getAcceptedDataProtection(): ?\DateTimeInterface
    {
        return $this->acceptedDataProtection;
    }

    public function setAcceptedDataProtection(?\DateTimeInterface $acceptedDataProtection): static
    {
        $this->acceptedDataProtection = $acceptedDataProtection;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getBirthdate(): ?\DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(?\DateTimeInterface $birthdate): static
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }

    public function setMobilePhone(?string $mobilePhone): static
    {
        $this->mobilePhone = $mobilePhone;

        return $this;
    }

    /**
     * @return Collection<int, UserAction>
     */
    public function getUserActions(): Collection
    {
        return $this->userActions;
    }

    public function addUserAction(UserAction $userAction): static
    {
        if (!$this->userActions->contains($userAction)) {
            $this->userActions->add($userAction);
            $userAction->setUser($this);
        }

        return $this;
    }

    public function removeUserAction(UserAction $userAction): static
    {
        if ($this->userActions->removeElement($userAction)) {
            // set the owning side to null (unless already changed)
            if ($userAction->getUser() === $this) {
                $userAction->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdminAction>
     */
    public function getAdminActions(): Collection
    {
        return $this->adminActions;
    }

    public function addAdminAction(AdminAction $adminAction): static
    {
        if (!$this->adminActions->contains($adminAction)) {
            $this->adminActions->add($adminAction);
            $adminAction->setUser($this);
        }

        return $this;
    }

    public function removeAdminAction(AdminAction $adminAction): static
    {
        if ($this->adminActions->removeElement($adminAction)) {
            // set the owning side to null (unless already changed)
            if ($adminAction->getUser() === $this) {
                $adminAction->setUser(null);
            }
        }

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
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setUser($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getUser() === $this) {
                $booking->setUser(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setUser($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getUser() === $this) {
                $invoice->setUser(null);
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
            $voucher->setUser($this);
        }

        return $this;
    }

    public function removeVoucher(Voucher $voucher): static
    {
        if ($this->vouchers->removeElement($voucher)) {
            // set the owning side to null (unless already changed)
            if ($voucher->getUser() === $this) {
                $voucher->setUser(null);
            }
        }

        return $this;
    }

    public function getStreet(): string
    {
        return $this->street ?? '';
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostCode(): string
    {
        return $this->postCode ?? '';
    }

    public function setPostCode(?string $postCode): static
    {
        $this->postCode = $postCode;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city ?? '';
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function hasAddress(): bool
    {
        return null !== $this->street && null !== $this->postCode && null !== $this->city;
    }

    public function getValidVouchers(): Collection
    {
        return $this->vouchers->filter(static fn (Voucher $voucher) => $voucher->isValid());
    }
}
