<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserActionsRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: UserActionsRepository::class)]
class UserAction
{
    use TimestampableEntity;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userActions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $form = null;

    /**
     * @var array<mixed, mixed>
     */
    #[ORM\Column]
    private array $data = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestUri = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getForm(): ?string
    {
        return $this->form;
    }

    public function setForm(string $form): static
    {
        $this->form = $form;

        return $this;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    public function setRequestUri(?string $requestUri): static
    {
        $this->requestUri = $requestUri;

        return $this;
    }

    public function getDataString(): string
    {
        $encodedData = json_encode($this->data);

        if (false === $encodedData) {
            return '';
        }

        return $encodedData;
    }
}
