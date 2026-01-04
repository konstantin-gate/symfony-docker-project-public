<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Entity;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use Brick\Math\BigDecimal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Entita reprezentující směnný kurz mezi dvěma měnami v určitém čase.
 */
#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'wallet_exchange_rate')]
#[ORM\Index(name: 'idx_rate_lookup', columns: ['base_currency', 'target_currency', 'fetched_at'])]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 3, enumType: CurrencyEnum::class)]
    private CurrencyEnum $baseCurrency;

    #[ORM\Column(type: 'string', length: 3, enumType: CurrencyEnum::class)]
    private CurrencyEnum $targetCurrency;

    /**
     * Uložení kurzu jako řetězec pro zachování přesnosti BigDecimal.
     * Délka 64 znaků je dostatečná i pro velmi malé nebo velké kurzy (např. krypto).
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $rate;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        CurrencyEnum $baseCurrency,
        CurrencyEnum $targetCurrency,
        BigDecimal $rate,
        ?\DateTimeImmutable $fetchedAt = null,
    ) {
        $this->baseCurrency = $baseCurrency;
        $this->targetCurrency = $targetCurrency;
        $this->rate = (string) $rate;
        $this->fetchedAt = $fetchedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getBaseCurrency(): CurrencyEnum
    {
        return $this->baseCurrency;
    }

    public function getTargetCurrency(): CurrencyEnum
    {
        return $this->targetCurrency;
    }

    /**
     * Získání kurzu jako objektu BigDecimal.
     */
    public function getRate(): BigDecimal
    {
        return BigDecimal::of($this->rate);
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
