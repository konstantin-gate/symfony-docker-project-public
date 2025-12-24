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

/**
 * Entita reprezentující kontakt pro zasílání pozdravů.
 */
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
     * Inicializuje nový kontakt a vygeneruje token pro odhlášení.
     *
     * @throws RandomException
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        // Generujeme token ihned při vytvoření
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    /**
     * Vrátí unikátní identifikátor kontaktu.
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * Nastaví unikátní identifikátor kontaktu.
     */
    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Vrátí e-mailovou adresu.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Nastaví e-mailovou adresu (převede na malá písmena).
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email !== null ? mb_strtolower($email) : null;

        return $this;
    }

    /**
     * Vrátí preferovaný jazyk kontaktu.
     */
    public function getLanguage(): GreetingLanguage
    {
        return $this->language;
    }

    /**
     * Nastaví preferovaný jazyk kontaktu.
     */
    public function setLanguage(GreetingLanguage $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Vrátí aktuální stav kontaktu.
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * Nastaví stav kontaktu.
     */
    public function setStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Vrátí token pro odhlášení z odběru.
     */
    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    /**
     * Nastaví token pro odhlášení z odběru.
     */
    public function setUnsubscribeToken(?string $unsubscribeToken): self
    {
        $this->unsubscribeToken = $unsubscribeToken;

        return $this;
    }

    /**
     * Vrátí datum a čas vytvoření kontaktu.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Nastaví datum a čas vytvoření kontaktu.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
