<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Embeddable]
class BillingAddress
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostCode(): ?string
    {
        return $this->postCode;
    }

    public function setPostCode(?string $postCode): static
    {
        $this->postCode = $postCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function isEmpty(): bool
    {
        foreach ($this->getFields() as $field) {
            if (null !== $field && '' !== trim($field)) {
                return false;
            }
        }

        return true;
    }

    public function isComplete(): bool
    {
        foreach ($this->getFields() as $field) {
            if (null === $field || '' === trim($field)) {
                return false;
            }
        }

        return true;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->isEmpty() || $this->isComplete()) {
            return;
        }

        $context->buildViolation('user.billing_address.incomplete')
                ->addViolation()
        ;
    }

    /**
     * @return array<string, ?string>
     */
    private function getFields(): array
    {
        return [
            'name'     => $this->name,
            'street'   => $this->street,
            'postCode' => $this->postCode,
            'city'     => $this->city,
        ];
    }
}
