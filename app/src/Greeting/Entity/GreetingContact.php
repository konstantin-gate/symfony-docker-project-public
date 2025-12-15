<?php

declare(strict_types=1);

namespace App\Greeting\Entity;

use App\Enum\Status;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Random\RandomException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GreetingContactRepository::class)]
#[ORM\Table(name: 'greeting_contact')]
class GreetingContact
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'string', enumType: GreetingLanguage::class, options: ['default' => 'cs'])]
    private GreetingLanguage $language = GreetingLanguage::Czech;

    #[ORM\Column(type: 'string', enumType: Status::class, options: ['default' => 'active'])]
    private Status $status = Status::Active;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $unsubscribeToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @throws RandomException
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        // Generujeme token ihned při vytvoření
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getLanguage(): GreetingLanguage
    {
        return $this->language;
    }

    public function setLanguage(GreetingLanguage $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): self
    {
        $this->unsubscribeToken = $unsubscribeToken;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
