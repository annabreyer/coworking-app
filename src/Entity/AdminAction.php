<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminActionRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: AdminActionRepository::class)]
class AdminAction
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'adminActions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?User $adminUser = null;

    /**
     * @var array<mixed, mixed>
     */
    #[ORM\Column]
    private array $data = [];

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

    public function getAdminUser(): ?User
    {
        return $this->adminUser;
    }

    public function setAdminUser(?User $adminUser): static
    {
        $this->adminUser = $adminUser;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed[] $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getDataString(): string
    {
        $json = json_encode($this->data);

        if (false === $json) {
            throw new \RuntimeException('Could not encode data to JSON');
        }

        return $json;
    }
}
