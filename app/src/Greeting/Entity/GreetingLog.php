<?php

declare(strict_types=1);

namespace App\Greeting\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entita reprezentující záznam o odeslání pozdravu.
 */
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

    /**
     * Vytvoří nový záznam o odeslání pro daný kontakt a rok.
     */
    public function __construct(GreetingContact $contact, int $year)
    {
        $this->contact = $contact;
        $this->year = $year;
        $this->sentAt = new \DateTimeImmutable();
    }

    /**
     * Vrátí unikátní identifikátor logu.
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * Nastaví unikátní identifikátor logu.
     */
    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Vrátí kontakt, kterému byl pozdrav odeslán.
     */
    public function getContact(): ?GreetingContact
    {
        return $this->contact;
    }

    /**
     * Nastaví kontakt pro tento log.
     */
    public function setContact(?GreetingContact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Vrátí datum a čas odeslání.
     */
    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * Nastaví datum a čas odeslání.
     */
    public function setSentAt(\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    /**
     * Vrátí rok, za který byl pozdrav odeslán.
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * Nastaví rok odeslání.
     */
    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }
}
