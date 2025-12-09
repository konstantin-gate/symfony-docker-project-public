<?php

declare(strict_types=1);

namespace App\Greeting\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'greeting_log')]
class GreetingLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: GreetingContact::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GreetingContact $contact = null;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $year;

    public function __construct(GreetingContact $contact, int $year)
    {
        $this->contact = $contact;
        $this->year = $year;
        $this->sentAt = new \DateTimeImmutable();
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

    public function getContact(): ?GreetingContact
    {
        return $this->contact;
    }

    public function setContact(?GreetingContact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }
}
